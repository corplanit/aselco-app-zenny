<?php

namespace App\Services\Ai;

use App\Models\Ticket;
use App\Models\TicketAiAnalysis;
use App\Models\TicketAiResponseDraft;
use App\Models\User;
use App\Services\Ai\Contracts\AiCompletionClient;
use App\Services\Rag\KnowledgeRetriever;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * CSR response assistance. Drafts are never sent to customers without human approval.
 */
class TicketAiResponseAssistant
{
    public function __construct(
        private AiCompletionClient $client,
        private PromptGuard $guard,
        private KnowledgeRetriever $retriever,
        private TicketAiAnalyzer $analyzer,
    ) {}

    public function suggest(Ticket $ticket, User $actor, ?string $guidance = null): TicketAiResponseDraft
    {
        $ticket->loadMissing(['category', 'latestAiAnalysis', 'actions']);
        $analysis = $ticket->latestAiAnalysis;

        if ($analysis === null && (bool) config('tickets.ai.enabled', true)) {
            try {
                $analysis = $this->analyzer->analyze($ticket, $actor);
            } catch (Throwable $e) {
                Log::warning('ticket.ai.response_analyze_failed', ['error' => $e->getMessage()]);
            }
        }

        $description = $this->guard->sanitizeUserMessage((string) $ticket->description);
        $guidanceSafe = $guidance ? $this->guard->sanitizeUserMessage($guidance) : null;

        $draftText = $analysis?->suggested_response
            ?: 'Thank you for contacting ASELCO. We are reviewing your concern ('.$ticket->ticket_no.') and will follow our official service workflow.';

        $source = 'analysis';
        if ($this->client->isConfigured() && ! $this->guard->looksLikeInjection($description)) {
            try {
                $retrieval = $this->retriever->retrieve(
                    $description.($guidanceSafe ? ' '.$guidanceSafe : ''),
                    'ticket-response',
                    $actor,
                    null
                );
                $knowledge = $this->retriever->buildContext($retrieval['hits'] ?? []);
                $completion = $this->client->completeJson(
                    (string) config('ai.prompts.admin'),
                    [[
                        'role' => 'user',
                        'content' => $this->guard->wrapUntrusted(
                            "Draft a customer reply for CSR review only. Do not claim the issue is fixed.\n"
                            ."Ticket {$ticket->ticket_no} status={$ticket->status} category=".($ticket->category?->name ?? '')."\n"
                            ."Summary: ".($analysis?->summary ?? 'n/a')."\n"
                            ."Knowledge:\n".($knowledge !== '' ? $knowledge : 'none')."\n"
                            ."Customer text:\n{$description}\n"
                            .($guidanceSafe ? "CSR guidance:\n{$guidanceSafe}\n" : '')
                        ),
                    ]]
                );
                $validated = app(AiResponseValidator::class)->validateAdmin($completion->payload, $draftText);
                $draftText = $validated['draft'];
                $source = 'openai';
            } catch (Throwable $e) {
                Log::warning('ticket.ai.response_fallback', ['code' => $e->getMessage()]);
            }
        }

        return TicketAiResponseDraft::query()->create([
            'ticket_id' => $ticket->id,
            'analysis_id' => $analysis?->id,
            'draft' => Str::limit($draftText, 4000, ''),
            'status' => TicketAiResponseDraft::STATUS_PENDING,
            'confidence' => $analysis?->confidence,
            'auto_send_eligible' => false,
            'auto_sent' => false,
            'created_by_ai' => true,
            'meta' => [
                'source' => $source,
                'requires_human_approval' => true,
                'guidance' => $guidanceSafe,
            ],
        ]);
    }

    /**
     * CSR reviews/edits. Does not send to the customer channel automatically.
     *
     * @return array{draft: TicketAiResponseDraft, sent: bool, message: string}
     */
    public function review(User $actor, TicketAiResponseDraft $draft, string $decision, ?string $editedBody = null, ?string $notes = null): array
    {
        if ($draft->status !== TicketAiResponseDraft::STATUS_PENDING) {
            throw new HttpException(422, 'Draft is not pending review.');
        }

        if ($decision === 'reject') {
            $draft->forceFill([
                'status' => TicketAiResponseDraft::STATUS_REJECTED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            return [
                'draft' => $draft->fresh(),
                'sent' => false,
                'message' => 'AI draft rejected. Nothing was sent to the customer.',
            ];
        }

        if ($decision !== 'approve') {
            throw new HttpException(422, 'Decision must be approve or reject.');
        }

        $body = $editedBody !== null && trim($editedBody) !== ''
            ? $this->guard->sanitizeUserMessage($editedBody)
            : $draft->bodyForSend();

        $draft->forceFill([
            'edited_body' => $body,
            'status' => TicketAiResponseDraft::STATUS_APPROVED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
            'auto_sent' => false,
            'meta' => array_merge($draft->meta ?? [], [
                'approved_for_send' => true,
                'send_channel' => 'manual_csr',
                'note' => 'Approved for CSR to copy/send via existing support channels. System does not auto-message customers.',
            ]),
        ])->save();

        return [
            'draft' => $draft->fresh(),
            'sent' => false,
            'message' => 'Draft approved for CSR use. It was not auto-sent; use Support/call channels to deliver.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $analyses = TicketAiAnalysis::query();
        $total = (clone $analyses)->count();
        $ok = (clone $analyses)->where('ok', true)->count();
        $overrides = (clone $analyses)->where('human_override', true)->count();
        $categoryMatch = (clone $analyses)->where('category_matches_submitted', true)->count();

        $sentiment = TicketAiAnalysis::query()
            ->selectRaw('sentiment, COUNT(*) as total')
            ->whereNotNull('sentiment')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment')
            ->all();

        $priority = TicketAiAnalysis::query()
            ->selectRaw('recommended_priority, COUNT(*) as total')
            ->whereNotNull('recommended_priority')
            ->groupBy('recommended_priority')
            ->pluck('total', 'recommended_priority')
            ->all();

        $drafts = TicketAiResponseDraft::query();
        $draftTotal = (clone $drafts)->count();
        $pending = (clone $drafts)->where('status', TicketAiResponseDraft::STATUS_PENDING)->count();
        $approved = (clone $drafts)->where('status', TicketAiResponseDraft::STATUS_APPROVED)->count();
        $rejected = (clone $drafts)->where('status', TicketAiResponseDraft::STATUS_REJECTED)->count();
        $sent = (clone $drafts)->where('status', TicketAiResponseDraft::STATUS_SENT)->count();
        $avgConfidence = TicketAiAnalysis::query()
            ->where('ok', true)
            ->whereNotNull('confidence')
            ->avg('confidence');

        $recent = TicketAiAnalysis::query()
            ->with(['ticket:id,ticket_no', 'recommendedCategory:id,name'])
            ->latest('analyzed_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(static fn (TicketAiAnalysis $row): array => [
                'id' => $row->id,
                'ticket_id' => $row->ticket_id,
                'ticket_no' => $row->ticket?->ticket_no,
                'recommended_category' => $row->recommendedCategory?->name,
                'recommended_priority' => $row->recommended_priority,
                'sentiment' => $row->sentiment,
                'confidence' => $row->confidence !== null ? (int) round(((float) $row->confidence) * 100) : null,
                'ok' => (bool) $row->ok,
                'category_matches_submitted' => (bool) $row->category_matches_submitted,
                'human_override' => (bool) $row->human_override,
                'analyzed_at' => optional($row->analyzed_at)?->timezone('Asia/Manila')?->toIso8601String(),
            ])
            ->all();

        return [
            'analyzed_tickets' => $total,
            'analysis_ok' => $ok,
            'analysis_failed' => max(0, $total - $ok),
            'analysis_ok_rate' => $total > 0 ? round(($ok / $total) * 100, 1) : 0,
            'category_match_rate' => $total > 0 ? round(($categoryMatch / $total) * 100, 1) : 0,
            'avg_confidence' => $avgConfidence !== null ? round(((float) $avgConfidence) * 100, 1) : 0,
            'human_overrides' => $overrides,
            'sentiment_distribution' => $sentiment,
            'priority_recommendations' => $priority,
            'response_drafts' => $draftTotal,
            'drafts_pending_review' => $pending,
            'drafts_approved' => $approved,
            'drafts_rejected' => $rejected,
            'drafts_sent' => $sent,
            'recent' => $recent,
            'auto_send_enabled' => (bool) config('tickets.ai.auto_send_low_risk_responses', false),
        ];
    }
}
