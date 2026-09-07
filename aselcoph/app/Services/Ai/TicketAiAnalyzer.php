<?php

namespace App\Services\Ai;

use App\Models\Ticket;
use App\Models\TicketAiAnalysis;
use App\Models\TicketAiResponseDraft;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Ai\Contracts\AiCompletionClient;
use App\Services\Rag\KnowledgeRetriever;
use App\Services\TicketService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Advisory AI analysis for tickets. Never mutates ticket category/priority/assignment
 * unless an authorized staff action explicitly applies a recommendation via TicketService.
 */
class TicketAiAnalyzer
{
    public function __construct(
        private AiCompletionClient $client,
        private PromptGuard $guard,
        private TicketService $tickets,
        private KnowledgeRetriever $retriever,
    ) {}

    public function analyze(Ticket $ticket, ?User $actor = null): TicketAiAnalysis
    {
        $ticket->loadMissing(['category', 'customer:id,name', 'actions', 'statusHistory']);

        $categories = TicketCategory::query()->orderBy('id')->get(['id', 'name', 'department_code', 'sla_minutes', 'requires_tsd_check', 'requires_payment_check']);
        $catalog = $categories->map(fn (TicketCategory $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'department_code' => $c->department_code,
        ])->values()->all();

        $description = $this->guard->sanitizeUserMessage((string) $ticket->description);
        $injection = $this->guard->looksLikeInjection($description);

        $retrieval = ['hits' => [], 'sufficient' => false];
        if ((bool) config('rag.enabled', true) && ! $injection) {
            try {
                $retrieval = $this->retriever->retrieve($description, 'ticket-analysis', $actor, null);
            } catch (Throwable $e) {
                Log::warning('ticket.ai.rag_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
            }
        }

        $fallback = $this->heuristic($ticket, $categories, $description, $injection);
        $payload = $fallback;
        $source = 'fallback';
        $model = null;
        $ok = true;
        $error = null;

        if ($this->client->isConfigured() && ! $injection && (bool) config('tickets.ai.enabled', true)) {
            try {
                $knowledge = $this->retriever->buildContext($retrieval['hits'] ?? []);
                $completion = $this->client->completeJson(
                    (string) config('ai.prompts.ticket_analysis'),
                    [[
                        'role' => 'user',
                        'content' => $this->guard->wrapUntrusted($this->promptBody($ticket, $catalog, $knowledge, $description)),
                    ]]
                );
                $payload = $this->validatePayload($completion->payload, $fallback, $categories);
                $source = 'openai';
                $model = $completion->model;
            } catch (Throwable $e) {
                $ok = false;
                $error = $e instanceof AiProviderException ? $e->errorCode : 'AI_UNEXPECTED';
                Log::warning('ticket.ai.analyze_fallback', [
                    'ticket_id' => $ticket->id,
                    'code' => $error,
                ]);
            }
        }

        $recommendedCategory = TicketCategory::query()->find($payload['recommended_category_id'])
            ?? $ticket->category
            ?? TicketCategory::query()->findOrFail($ticket->category_id);
        $authoritativeDept = $this->tickets->authoritativeDepartmentFor($recommendedCategory);
        $suggestedAssignee = $this->tickets->previewAssigneeId(
            $authoritativeDept,
            $recommendedCategory->default_assignee_role
        );

        $aiSuggestedDept = $payload['suggested_department'] ?? null;
        $routing = [
            'authoritative_department' => $authoritativeDept,
            'ai_suggested_department' => $aiSuggestedDept,
            'department_accepted' => $aiSuggestedDept === null || $aiSuggestedDept === $authoritativeDept,
            'tsd_department' => (string) config('tickets.assignment.tsd_department', 'TSD'),
            'requires_tsd_check' => (bool) $recommendedCategory->requires_tsd_check,
            'requires_payment_check' => (bool) $recommendedCategory->requires_payment_check,
            'assignment_strategy' => (string) config('tickets.assignment.strategy', 'round_robin'),
            'note' => 'Existing TicketService routing remains authoritative. AI cannot bypass day/night, TSD, or role rules.',
        ];

        // Force suggested department to authoritative value — AI cannot override routing.
        $suggestedDepartment = $authoritativeDept;

        $analysis = TicketAiAnalysis::query()->create([
            'ticket_id' => $ticket->id,
            'recommended_category_id' => $recommendedCategory->id,
            'category_matches_submitted' => (int) $recommendedCategory->id === (int) $ticket->category_id,
            'recommended_priority' => $payload['recommended_priority'],
            'sentiment' => $payload['sentiment'],
            'confidence' => $payload['confidence'],
            'summary' => $payload['summary'],
            'suggested_department' => $suggestedDepartment,
            'suggested_assignee_id' => $suggestedAssignee,
            'recommended_next_action' => $payload['recommended_next_action'],
            'suggested_response' => $payload['suggested_response'],
            'routing_validation' => $routing,
            'knowledge_citations' => array_map(fn ($hit) => method_exists($hit, 'toArray') ? $hit->toArray() : $hit, $retrieval['hits'] ?? []),
            'raw_payload' => [
                'rationale' => $payload['rationale'] ?? null,
                'provider' => $source,
            ],
            'model' => $model,
            'source' => $source,
            'ok' => $ok,
            'error_code' => $error,
            'analyzed_at' => now(),
        ]);

        if (filled($payload['suggested_response'])) {
            $eligible = $this->isAutoSendEligible($payload);
            $draft = TicketAiResponseDraft::query()->create([
                'ticket_id' => $ticket->id,
                'analysis_id' => $analysis->id,
                'draft' => $payload['suggested_response'],
                'status' => TicketAiResponseDraft::STATUS_PENDING,
                'confidence' => $payload['confidence'],
                'auto_send_eligible' => $eligible,
                'auto_sent' => false,
                'created_by_ai' => true,
                'meta' => [
                    'requires_human_approval' => true,
                    'auto_send_configured' => (bool) config('tickets.ai.auto_send_low_risk_responses', false),
                ],
            ]);

            // Auto-send is opt-in and still does not send externally in this phase —
            // mark eligible only; CSR must approve. When auto_send is enabled we only
            // flag the draft; we never dispatch customer messages without an approval path.
            if ($eligible && (bool) config('tickets.ai.auto_send_low_risk_responses', false)) {
                $draft->forceFill([
                    'meta' => array_merge($draft->meta ?? [], [
                        'auto_send_queued' => true,
                        'note' => 'Configured for future low-risk auto-send; still requires outbound channel integration and remains unsent.',
                    ]),
                ])->save();
            }
        }

        return $analysis->fresh(['recommendedCategory', 'suggestedAssignee']);
    }

    /**
     * Apply a staff-approved priority recommendation through the normal ticket update path.
     * Does not change category without explicit category_id (preserves customer submission).
     */
    public function applyPriorityRecommendation(User $actor, Ticket $ticket, TicketAiAnalysis $analysis, ?string $reason = null): Ticket
    {
        $priority = $analysis->recommended_priority;
        if (! in_array($priority, TicketAiAnalysis::PRIORITIES, true)) {
            throw new \InvalidArgumentException('Invalid recommended priority.');
        }

        $ticket->forceFill(['priority' => $priority])->save();

        $analysis->forceFill([
            'human_override' => true,
            'override_field' => 'priority',
            'override_reason' => $reason ?: 'Staff applied AI priority recommendation.',
            'override_by' => $actor->id,
            'override_at' => now(),
        ])->save();

        return $ticket->fresh();
    }

    /**
     * @param  list<TicketCategory>|\Illuminate\Support\Collection  $categories
     * @return array<string, mixed>
     */
    private function heuristic(Ticket $ticket, $categories, string $description, bool $injection): array
    {
        $p = mb_strtolower($description);
        $pick = fn (string $dept) => $categories->firstWhere('department_code', $dept)?->id ?? $ticket->category_id;

        $categoryId = $ticket->category_id;
        $priority = $ticket->priority ?: 'normal';
        $sentiment = 'neutral';
        $action = 'endorse';
        $summary = 'Customer reported: '.Str::limit($description, 240, '…');

        if ($injection) {
            return [
                'recommended_category_id' => $ticket->category_id,
                'recommended_priority' => 'normal',
                'sentiment' => 'neutral',
                'confidence' => 0.2,
                'summary' => 'Possible prompt-injection language detected. Keeping submitted category; staff should review manually.',
                'recommended_next_action' => 'investigate',
                'suggested_response' => 'Thank you for contacting ASELCO. A customer service representative will review your concern through our official ticket process.',
                'suggested_department' => null,
                'rationale' => 'injection_guard',
            ];
        }

        if ($this->has($p, ['spark', 'downed', 'electrocution', 'fire', 'explosion', 'dangerous'])) {
            $categoryId = $pick('COMD') ?? $pick('TSD') ?? $categoryId;
            $priority = 'urgent';
            $sentiment = 'urgent_distressed';
            $action = 'escalate_tsd';
        } elseif ($this->has($p, ['outage', 'no power', 'brownout', 'blackout', 'interruption'])) {
            $categoryId = $pick('COMD') ?? $categoryId;
            $priority = $this->has($p, ['whole barangay', 'entire', 'many customers', 'feeder']) ? 'high' : 'high';
            $sentiment = $this->has($p, ['angry', 'furious', 'unacceptable']) ? 'highly_negative' : 'negative';
            $action = 'endorse';
        } elseif ($this->has($p, ['bill', 'billing', 'overcharge', 'payment', 'ast', 'collection'])) {
            $categoryId = $pick('CCAD') ?? $categoryId;
            $priority = $this->has($p, ['disconnection', 'disconnect']) ? 'high' : 'normal';
            $sentiment = $this->has($p, ['wrong', 'dispute', 'overcharge']) ? 'negative' : 'neutral';
            $action = $this->has($p, ['payment', 'pay']) ? 'await_payment' : 'investigate';
        } elseif ($this->has($p, ['meter', 'kwh', 'connection', 'reconnection', 'new service'])) {
            $categoryId = $pick('AO-CDS') ?? $categoryId;
            $priority = 'normal';
            $action = 'investigate';
        } elseif ($this->has($p, ['institutional', 'cooperative', 'membership policy'])) {
            $categoryId = $pick('ISD-CCSMDD') ?? $categoryId;
        }

        if ($this->has($p, ['angry', 'furious', 'lawsuit', 'complain to'])) {
            $sentiment = 'highly_negative';
        } elseif ($this->has($p, ['thank', 'appreciate', 'please help'])) {
            $sentiment = str_contains($p, 'thank') ? 'positive' : $sentiment;
        }

        $cat = $categories->firstWhere('id', $categoryId);

        return [
            'recommended_category_id' => $categoryId,
            'recommended_priority' => $priority,
            'sentiment' => $sentiment,
            'confidence' => 0.55,
            'summary' => $summary,
            'recommended_next_action' => $action,
            'suggested_response' => 'Thank you for contacting ASELCO. We received your concern'
                .($cat ? ' regarding '.$cat->name : '')
                .'. A staff member will review it in our ticket system. We cannot change bills or dispatch crews from this message alone.',
            'suggested_department' => $cat?->department_code,
            'rationale' => 'heuristic',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fallback
     * @param  \Illuminate\Support\Collection  $categories
     * @return array<string, mixed>
     */
    private function validatePayload(array $payload, array $fallback, $categories): array
    {
        $ids = $categories->pluck('id')->all();
        $categoryId = (int) ($payload['recommended_category_id'] ?? 0);
        if (! in_array($categoryId, $ids, true)) {
            $categoryId = (int) $fallback['recommended_category_id'];
        }

        $priority = (string) ($payload['recommended_priority'] ?? '');
        if (! in_array($priority, TicketAiAnalysis::PRIORITIES, true)) {
            $priority = $fallback['recommended_priority'];
        }

        $sentiment = (string) ($payload['sentiment'] ?? '');
        if (! in_array($sentiment, TicketAiAnalysis::SENTIMENTS, true)) {
            $sentiment = $fallback['sentiment'];
        }

        $confidence = (float) ($payload['confidence'] ?? $fallback['confidence']);
        $confidence = max(0, min(1, $confidence));

        $actions = ['endorse', 'assign', 'investigate', 'contact_customer', 'escalate_tsd', 'await_payment', 'none'];
        $next = (string) ($payload['recommended_next_action'] ?? '');
        if (! in_array($next, $actions, true)) {
            $next = $fallback['recommended_next_action'];
        }

        $summary = trim((string) ($payload['summary'] ?? ''));
        if ($summary === '') {
            $summary = $fallback['summary'];
        }

        $response = trim((string) ($payload['suggested_response'] ?? ''));
        if ($response === '') {
            $response = $fallback['suggested_response'];
        }

        $dept = $payload['suggested_department'] ?? $fallback['suggested_department'] ?? null;
        if (is_string($dept) && $dept !== '') {
            $allowedDepts = $categories->pluck('department_code')->unique()->all();
            $allowedDepts[] = (string) config('tickets.assignment.distribution_day_department', 'COMD');
            $allowedDepts[] = (string) config('tickets.assignment.distribution_night_department', 'GUARD');
            $allowedDepts[] = (string) config('tickets.assignment.tsd_department', 'TSD');
            if (! in_array($dept, $allowedDepts, true)) {
                $dept = null;
            }
        } else {
            $dept = null;
        }

        return [
            'recommended_category_id' => $categoryId,
            'recommended_priority' => $priority,
            'sentiment' => $sentiment,
            'confidence' => $confidence,
            'summary' => Str::limit($summary, 2000, ''),
            'recommended_next_action' => $next,
            'suggested_response' => Str::limit($response, 4000, ''),
            'suggested_department' => $dept,
            'rationale' => Str::limit((string) ($payload['rationale'] ?? ''), 1000, ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $catalog
     */
    private function promptBody(Ticket $ticket, array $catalog, string $knowledge, string $description): string
    {
        $history = $ticket->actions->take(5)->map(fn ($a) => '- '.$a->action_taken)->implode("\n");
        $statuses = $ticket->statusHistory->take(5)->map(fn ($h) => '- '.$h->from_status.' → '.$h->to_status)->implode("\n");

        return "Ticket catalog (only valid category ids):\n".json_encode($catalog)
            ."\n\nTicket:\n"
            ."- id: {$ticket->id}\n"
            ."- ticket_no: {$ticket->ticket_no}\n"
            ."- submitted_category_id: {$ticket->category_id}\n"
            ."- submitted_category: ".($ticket->category?->name ?? '')."\n"
            ."- channel: {$ticket->channel}\n"
            ."- status: {$ticket->status}\n"
            ."- priority: {$ticket->priority}\n"
            ."- subcategory: ".($ticket->subcategory ?? '')."\n"
            ."\nRecent actions:\n".($history !== '' ? $history : '- none')
            ."\nStatus history:\n".($statuses !== '' ? $statuses : '- none')
            ."\n\nAPPROVED KNOWLEDGE (optional):\n".($knowledge !== '' ? $knowledge : 'none')
            ."\n\nCustomer description:\n{$description}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isAutoSendEligible(array $payload): bool
    {
        $min = (float) config('tickets.ai.min_confidence_for_auto_send', 0.85);
        $safeSentiment = in_array($payload['sentiment'], ['positive', 'neutral'], true);
        $safeAction = in_array($payload['recommended_next_action'], ['none', 'contact_customer'], true);

        return $safeSentiment
            && $safeAction
            && (float) $payload['confidence'] >= $min;
    }

    /**
     * @param  list<string>  $needles
     */
    private function has(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
