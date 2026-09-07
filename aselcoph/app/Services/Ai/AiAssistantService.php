<?php

namespace App\Services\Ai;

use App\Models\AiAdminAuditLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiRequestLog;
use App\Models\KnowledgeChunk;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\Contracts\AiCompletionClient;
use App\Services\Rag\GroundedResponder;
use App\Services\Rag\KnowledgeRetriever;
use App\Services\Rag\RetrievalHit;
use App\Services\TicketService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantService
{
    public function __construct(
        private AiCompletionClient $client,
        private PromptGuard $guard,
        private AiResponseValidator $validator,
        private LocalAiFallback $fallback,
        private TicketService $tickets,
        private KnowledgeRetriever $retriever,
        private GroundedResponder $grounded,
        private CustomerContextBuilder $contextBuilder,
        private EscalationDetector $escalationDetector,
        private ConversationCoach $coach,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function chat(User $user, string $message, ?int $conversationId, ?string $ip, string $channel = 'customer'): array
    {
        $conversation = $this->conversationFor($user, $conversationId, $channel);
        $sanitized = $this->guard->sanitizeUserMessage($message);
        $injection = $this->guard->looksLikeInjection($sanitized);
        $history = $this->historyForProvider($conversation);
        $owned = $this->contextBuilder->forAssistant($user, $sanitized);

        $this->appendMessage($conversation, 'user', $sanitized);
        $result = $this->completeCustomer($user, $sanitized, $history, $injection, $channel, $ip, $owned);
        $shaped = $this->shapeCustomer($result, $sanitized, $owned);

        $this->appendMessage($conversation, 'assistant', $shaped['reply'], $shaped);
        $conversation->forceFill([
            'title' => $conversation->title ?: Str::limit($sanitized, 80, ''),
            'last_message_at' => now(),
        ])->save();

        return $shaped + [
            'conversation_id' => $conversation->id,
            'source' => $result['source'],
            'suggested_questions' => $this->coach->suggestedQuestions(),
        ];
    }

    /**
     * One-shot inquiry (classification + guidance). Still persisted as a conversation.
     *
     * @return array<string, mixed>
     */
    public function inquiry(User $user, string $message, ?string $ip): array
    {
        $sanitized = $this->guard->sanitizeUserMessage($message);
        $injection = $this->guard->looksLikeInjection($sanitized);
        $conversation = $this->conversationFor($user, null, 'customer');
        $owned = $this->contextBuilder->forAssistant($user, $sanitized);
        $this->appendMessage($conversation, 'user', $sanitized);

        $result = $this->completeCustomer($user, $sanitized, [], $injection, 'inquiry', $ip, $owned);
        $shaped = $this->shapeCustomer($result, $sanitized, $owned);

        $this->appendMessage($conversation, 'assistant', $shaped['reply'], $shaped);
        $conversation->forceFill([
            'title' => Str::limit($sanitized, 80, ''),
            'last_message_at' => now(),
        ])->save();

        return $shaped + [
            'conversation_id' => $conversation->id,
            'source' => $result['source'],
            'workflow' => 'inquiry',
            'suggested_questions' => $this->coach->suggestedQuestions(),
        ];
    }

    /**
     * Retrieval-only endpoint (no generation).
     *
     * @return array<string, mixed>
     */
    public function searchKnowledge(User $user, string $message, ?string $ip): array
    {
        $sanitized = $this->guard->sanitizeUserMessage($message);
        $retrieval = $this->retriever->retrieve($sanitized, 'customer-search', $user, $ip);

        return [
            'query' => $sanitized,
            'sufficient' => $retrieval['sufficient'],
            'top_score' => round($retrieval['top_score'], 4),
            'hits' => array_map(fn (RetrievalHit $hit) => $hit->toArray(), $retrieval['hits']),
            'suggestions' => array_map(
                fn (RetrievalHit $hit) => [
                    'title' => $hit->title,
                    'excerpt' => Str::limit($hit->content, 160, '…'),
                    'score' => round($hit->score, 4),
                    'category' => $hit->metadata['category'] ?? null,
                ],
                $retrieval['hits']
            ),
        ];
    }

    public function deleteConversation(User $user, int $id, string $channel = 'customer'): bool
    {
        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereKey($id)
            ->first();

        if ($conversation === null) {
            return false;
        }

        $conversation->messages()->delete();
        $conversation->delete();

        return true;
    }

    /**
     * Escalate to human via existing ticket workflow (create or continue).
     * Never mutates billing/wallet records.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function escalate(User $user, array $payload, ?string $ip): array
    {
        $conversationId = isset($payload['conversation_id']) ? (int) $payload['conversation_id'] : null;
        $ticketId = isset($payload['ticket_id']) ? (int) $payload['ticket_id'] : null;
        $description = $this->guard->sanitizeUserMessage((string) ($payload['description'] ?? $payload['message'] ?? ''));
        $categoryHint = isset($payload['category_hint']) ? (string) $payload['category_hint'] : null;
        $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : null;

        $conversation = $conversationId
            ? AiConversation::query()
                ->where('user_id', $user->id)
                ->where('channel', 'customer')
                ->whereKey($conversationId)
                ->first()
            : null;

        // Continue an existing owned ticket — do not invent a parallel process.
        if ($ticketId) {
            try {
                $ticket = $this->tickets->showCustomer($user, $ticketId);
            } catch (Throwable) {
                return [
                    'message' => 'Ticket not found for this account.',
                    'code' => 'NOT_FOUND',
                    'escalated' => false,
                ];
            }

            $this->appendEscalationNote($conversation, $user, $ticket, 'continued');

            return [
                'escalated' => true,
                'mode' => 'continue_ticket',
                'applies_financial_changes' => false,
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'status' => $ticket->status,
                    'category' => $ticket->category?->only(['id', 'name', 'department_code']),
                ],
                'next_step' => [
                    'label' => 'View ticket',
                    'href' => '/tickets/'.$ticket->id,
                ],
                'message' => 'Your existing ticket remains in the official routing workflow. A CSR/department will continue from there.',
            ];
        }

        if ($description === '') {
            $description = 'Customer requested human assistance via the AI assistant.';
            if ($conversation) {
                $recent = $conversation->messages()->orderByDesc('id')->limit(4)->get()->reverse();
                $bits = $recent->map(fn (AiMessage $m) => strtoupper($m->role).': '.Str::limit($m->content, 220, ''))->all();
                if ($bits !== []) {
                    $description = "AI escalation summary (customer-owned conversation):\n".implode("\n", $bits);
                }
            }
        }

        $resolvedCategoryId = $this->contextBuilder->resolveCategoryId($categoryHint, $categoryId);
        if (! $resolvedCategoryId) {
            return [
                'message' => 'Unable to resolve a complaint category for escalation.',
                'code' => 'CATEGORY_REQUIRED',
                'escalated' => false,
            ];
        }

        $ticket = $this->tickets->createCustomerTicket($user, [
            'category_id' => $resolvedCategoryId,
            'description' => Str::limit($description, 5000, ''),
            'priority' => 'normal',
            'channel' => 'app',
            'subcategory' => 'ai_escalation',
        ]);

        $this->appendEscalationNote($conversation, $user, $ticket, 'created');

        AiAdminAuditLog::query()->create([
            'actor_id' => $user->id,
            'ticket_id' => $ticket->id,
            'action' => 'customer_ai_escalate',
            'ip_address' => $ip,
            'user_agent' => null,
            'meta' => [
                'conversation_id' => $conversation?->id,
                'category_hint' => $categoryHint,
                'mode' => 'create_ticket',
            ],
            'created_at' => now(),
        ]);

        return [
            'escalated' => true,
            'mode' => 'create_ticket',
            'applies_financial_changes' => false,
            'ticket' => [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'status' => $ticket->status,
                'category' => $ticket->category?->only(['id', 'name', 'department_code']),
            ],
            'next_step' => [
                'label' => 'View ticket',
                'href' => '/tickets/'.$ticket->id,
            ],
            'message' => 'A concern was filed in the official ticket system so CSR/department routing can continue.',
        ];
    }

    private function appendEscalationNote(?AiConversation $conversation, User $user, Ticket $ticket, string $mode): void
    {
        if ($conversation === null) {
            return;
        }

        $text = $mode === 'created'
            ? "Human escalation created ticket {$ticket->ticket_no}. Track it under My Tickets — I cannot change ticket status."
            : "Human escalation continues ticket {$ticket->ticket_no}. Track updates under My Tickets.";

        $this->appendMessage($conversation, 'assistant', $text, [
            'intent' => 'complaint',
            'suggested_action' => 'view_tickets',
            'escalate' => true,
            'category_hint' => $ticket->category?->department_code,
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();
    }

    /**
     * Staff copilot. Never mutates tickets, wallets, or billing.
     *
     * @return array<string, mixed>
     */
    public function adminAssist(User $actor, string $message, ?int $ticketId, ?string $ip, ?string $userAgent): array
    {
        $sanitized = $this->guard->sanitizeUserMessage($message);
        $injection = $this->guard->looksLikeInjection($sanitized);
        $ticketContext = $this->safeTicketContext($actor, $ticketId);

        $fallback = $this->fallback->admin($sanitized);
        $payload = $fallback;
        $source = 'fallback';
        $model = null;
        $ok = true;
        $error = null;
        $tokensIn = null;
        $tokensOut = null;
        $latency = 0;

        if ($this->client->isConfigured() && ! $injection) {
            try {
                $completion = $this->client->completeJson(
                    (string) config('ai.prompts.admin'),
                    [[
                        'role' => 'user',
                        'content' => $this->guard->wrapUntrusted(
                            $this->adminPromptBody($sanitized, $ticketContext)
                        ),
                    ]]
                );
                $payload = $this->validator->validateAdmin($completion->payload, $fallback['draft']);
                $source = 'openai';
                $model = $completion->model;
                $tokensIn = $completion->promptTokens;
                $tokensOut = $completion->completionTokens;
                $latency = $completion->latencyMs;
            } catch (Throwable $e) {
                $ok = false;
                $error = $e instanceof AiProviderException ? $e->errorCode : 'AI_UNEXPECTED';
                Log::warning('ai.admin.fallback', ['code' => $error]);
            }
        }

        $this->logUsage($actor->id, 'admin', $model, mb_strlen($sanitized), $tokensIn, $tokensOut, $latency, $ok, $error, $ip);

        AiAdminAuditLog::query()->create([
            'actor_id' => $actor->id,
            'ticket_id' => $ticketId,
            'action' => 'assist',
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 500, '') : null,
            'meta' => [
                'intent' => $payload['intent'],
                'escalate' => $payload['escalate'],
                'source' => $source,
                'ticket_no' => $ticketContext['ticket_no'] ?? null,
            ],
            'created_at' => now(),
        ]);

        return [
            'draft' => $payload['draft'],
            'intent' => $payload['intent'],
            'notes_for_agent' => $payload['notes_for_agent'],
            'escalate' => $payload['escalate'],
            'suggested_action' => 'none',
            'applies_changes' => false,
            'ticket' => $ticketContext,
            'source' => $source,
        ];
    }

    public function listConversations(User $user, int $perPage = 20, string $channel = 'customer'): LengthAwarePaginator
    {
        return AiConversation::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function (AiConversation $conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'updated_at' => $conversation->updated_at?->toIso8601String(),
                ];
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function showConversation(User $user, int $id, string $channel = 'customer'): ?array
    {
        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereKey($id)
            ->first();

        if ($conversation === null) {
            return null;
        }

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'messages' => $conversation->messages()
                ->orderBy('id')
                ->get()
                ->map(fn (AiMessage $message) => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'intent' => $message->intent,
                    'suggested_action' => $message->suggested_action,
                    'escalate' => $message->escalate,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  array{billing: mixed, tickets: list, categories: list, prompt_block: string}  $owned
     * @return array{
     *   payload: array{reply: string, intent: string, escalate: bool, suggested_action: string, category_hint: ?string},
     *   source: string,
     *   knowledge_used: bool,
     *   knowledge_sufficient: bool,
     *   citations: list<array<string, mixed>>,
     *   escalation: array{needs_human: bool, reason: string|null, force_action: string|null}
     * }
     */
    private function completeCustomer(
        User $user,
        string $sanitized,
        array $history,
        bool $injection,
        string $channel,
        ?string $ip,
        array $owned,
    ): array {
        $retrieval = ['hits' => [], 'sufficient' => false, 'top_score' => 0.0];
        if ((bool) config('rag.enabled', true) && ! $injection) {
            $retrieval = $this->retriever->retrieve($sanitized, $channel, $user, $ip);
        }

        $citations = array_map(fn (RetrievalHit $hit) => [
            'document_id' => $hit->documentId,
            'title' => $hit->title,
            'score' => round($hit->score, 4),
            'category' => $hit->metadata['category'] ?? null,
            'excerpt' => Str::limit($hit->content, 180, '…'),
        ], $retrieval['hits']);

        $grounded = $this->grounded->respond($sanitized, $retrieval, $injection);
        $hasIndexedKnowledge = KnowledgeChunk::query()->where('active', true)->exists();
        $fallback = $this->fallback->customer($sanitized, $injection);

        if ($grounded['grounded']) {
            $fallback = $grounded;
        } elseif ($hasIndexedKnowledge && (bool) config('rag.enabled', true) && ! $injection) {
            $fallback = $grounded;
        }

        // Enrich billing/ticket answers with owned facts without mutating anything.
        $fallback = $this->enrichWithOwnedFacts($fallback, $sanitized, $owned);

        $payload = [
            'reply' => $fallback['reply'],
            'intent' => $fallback['intent'],
            'escalate' => $fallback['escalate'],
            'suggested_action' => $fallback['suggested_action'],
            'category_hint' => $fallback['category_hint'],
        ];
        $source = $grounded['grounded'] ? 'rag' : 'fallback';
        $model = null;
        $ok = true;
        $error = null;
        $tokensIn = null;
        $tokensOut = null;
        $latency = 0;

        if ($this->client->isConfigured() && ! $injection) {
            try {
                $messages = $history;
                $messages[] = [
                    'role' => 'user',
                    'content' => $this->guard->wrapUntrusted(
                        $this->customerPromptBody($user, $sanitized, $retrieval, $owned)
                    ),
                ];
                $completion = $this->client->completeJson((string) config('ai.prompts.customer'), $messages);
                $payload = $this->validator->validateCustomer($completion->payload, $payload['reply']);
                if ($hasIndexedKnowledge && ! ($retrieval['sufficient'] ?? false)) {
                    $payload = $this->validator->validateCustomer($grounded, $grounded['reply']);
                    $source = 'rag-insufficient';
                } else {
                    $source = ($retrieval['sufficient'] ?? false) ? 'openai+rag' : 'openai';
                }
                $model = $completion->model;
                $tokensIn = $completion->promptTokens;
                $tokensOut = $completion->completionTokens;
                $latency = $completion->latencyMs;
            } catch (Throwable $e) {
                $ok = false;
                $error = $e instanceof AiProviderException ? $e->errorCode : 'AI_UNEXPECTED';
                Log::warning('ai.customer.fallback', ['code' => $error]);
            }
        }

        $escalation = $this->escalationDetector->detect($sanitized, $payload);
        if ($escalation['needs_human']) {
            $payload['escalate'] = true;
            if ($escalation['force_action'] && in_array($escalation['force_action'], AiResponseValidator::ACTIONS, true)) {
                $payload['suggested_action'] = $escalation['force_action'];
            }
        }

        $this->logUsage($user->id, $channel, $model, mb_strlen($sanitized), $tokensIn, $tokensOut, $latency, $ok, $error, $ip);

        return [
            'payload' => $payload,
            'source' => $source,
            'knowledge_used' => ($retrieval['hits'] ?? []) !== [],
            'knowledge_sufficient' => (bool) ($retrieval['sufficient'] ?? false),
            'citations' => $citations,
            'escalation' => $escalation,
        ];
    }

    /**
     * @param  array{reply: string, intent: string, escalate: bool, suggested_action: string, category_hint: ?string}  $payload
     * @param  array{billing: mixed, tickets: list, categories: list, prompt_block: string}  $owned
     * @return array{reply: string, intent: string, escalate: bool, suggested_action: string, category_hint: ?string}
     */
    private function enrichWithOwnedFacts(array $payload, string $message, array $owned): array
    {
        if ($payload['intent'] === 'billing' && is_array($owned['billing'] ?? null)) {
            $billing = $owned['billing'];
            $due = number_format((float) ($billing['amount_due'] ?? 0), 2);
            $ast = $billing['ast_balance'] !== null ? number_format((float) $billing['ast_balance'], 2) : 'n/a';
            $payload['reply'] = trim($payload['reply'].' For your linked account(s): amount due is PHP/AST '.$due
                .' across '.((int) ($billing['pending_bill_count'] ?? 0)).' pending bill(s); AST wallet balance is '.$ast
                .'. I cannot change these figures — use Pay or file a Billing & Collection concern if something looks wrong.');
        }

        if (($payload['intent'] === 'complaint' || $this->contextBuilder->looksTicketStatusRelated($message))
            && ($owned['tickets'] ?? []) !== []) {
            $list = collect($owned['tickets'])
                ->map(fn (array $t) => $t['ticket_no'].' ('.$t['status'].')')
                ->implode(', ');
            $payload['reply'] = trim($payload['reply'].' Your open tickets: '.$list.'. Open My Tickets for details — I cannot change status.');
            if ($payload['suggested_action'] === 'none') {
                $payload['suggested_action'] = 'view_tickets';
            }
        }

        return $payload;
    }

    /**
     * @param  array{
     *   payload: array{reply: string, intent: string, escalate: bool, suggested_action: string, category_hint: ?string},
     *   source: string,
     *   knowledge_used: bool,
     *   knowledge_sufficient: bool,
     *   citations: list<array<string, mixed>>,
     *   escalation: array{needs_human: bool, reason: string|null, force_action: string|null}
     * }  $result
     * @param  array{billing: mixed, tickets: list, categories: list, prompt_block: string}  $owned
     * @return array<string, mixed>
     */
    private function shapeCustomer(array $result, string $message, array $owned): array
    {
        $payload = $result['payload'];
        $action = $payload['suggested_action'];
        $escalate = (bool) $payload['escalate'];

        return [
            'reply' => $payload['reply'],
            'intent' => $payload['intent'],
            'escalate' => $escalate,
            'suggested_action' => $action,
            'category_hint' => $payload['category_hint'],
            'next_step' => $this->nextStep($action),
            'actions' => $this->coach->actions($action, $escalate),
            'follow_ups' => $this->coach->followUps($payload['intent'], $escalate, $result['citations']),
            'knowledge_used' => $result['knowledge_used'],
            'knowledge_sufficient' => $result['knowledge_sufficient'],
            'citations' => $result['citations'],
            'human_required' => (bool) ($result['escalation']['needs_human'] ?? false),
            'escalation_reason' => $result['escalation']['reason'] ?? null,
            'billing_snapshot' => $owned['billing'],
            'open_tickets' => $owned['tickets'],
            'complaint_categories' => $owned['categories'],
            'can_mutate_billing' => false,
            'can_mutate_tickets' => false,
        ];
    }

    /**
     * @return array{label: string, href: string|null}
     */
    private function nextStep(string $action): array
    {
        return match ($action) {
            'file_ticket' => ['label' => 'Report a concern', 'href' => '/complaints'],
            'view_tickets' => ['label' => 'View my tickets', 'href' => '/tabs/tickets'],
            'pay_bill' => ['label' => 'Pay with AST', 'href' => '/tabs/pay'],
            'contact_support' => ['label' => 'Open Support', 'href' => '/support'],
            default => ['label' => 'Continue chatting', 'href' => null],
        };
    }

    private function conversationFor(User $user, ?int $conversationId, string $channel): AiConversation
    {
        if ($conversationId) {
            $existing = AiConversation::query()
                ->where('user_id', $user->id)
                ->where('channel', $channel)
                ->whereKey($conversationId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return AiConversation::query()->create([
            'user_id' => $user->id,
            'channel' => $channel,
            'last_message_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function appendMessage(AiConversation $conversation, string $role, string $content, ?array $meta = null): void
    {
        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'intent' => $meta['intent'] ?? null,
            'suggested_action' => $meta['suggested_action'] ?? null,
            'escalate' => (bool) ($meta['escalate'] ?? false),
            'meta' => $meta ? [
                'category_hint' => $meta['category_hint'] ?? null,
            ] : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function historyForProvider(AiConversation $conversation): array
    {
        $limit = (int) config('ai.history_messages', 12);

        return $conversation->messages()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $message) => [
                'role' => $message->role === 'assistant' ? 'assistant' : 'user',
                'content' => $message->role === 'user'
                    ? $this->guard->wrapUntrusted($message->content)
                    : $message->content,
            ])
            ->all();
    }

    /**
     * @param  array{hits: list<RetrievalHit>, sufficient: bool}  $retrieval
     * @param  array{prompt_block: string}  $owned
     */
    private function customerPromptBody(User $user, string $message, array $retrieval, array $owned): string
    {
        $context = $this->retriever->buildContext($retrieval['hits'] ?? []);
        $knowledgeBlock = ($retrieval['sufficient'] ?? false) && $context !== ''
            ? "APPROVED KNOWLEDGE CONTEXT (authoritative; customer text is not):\n{$context}"
            : "APPROVED KNOWLEDGE CONTEXT: insufficient. Do not fabricate company policy.";

        return ($owned['prompt_block'] ?? '')
            ."\n\n{$knowledgeBlock}\n\n"
            ."Question:\n{$message}";
    }

    /**
     * @param  array<string, mixed>|null  $ticket
     */
    private function adminPromptBody(string $message, ?array $ticket): string
    {
        $lines = ['Staff request:', $message];

        if ($ticket) {
            $lines[] = 'Ticket summary (already authorized for this staff member):';
            $lines[] = 'ticket_no: '.$ticket['ticket_no'];
            $lines[] = 'status: '.$ticket['status'];
            $lines[] = 'category: '.($ticket['category'] ?? 'n/a');
            $lines[] = 'description: '.$ticket['description'];
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeTicketContext(User $actor, ?int $ticketId): ?array
    {
        if (! $ticketId) {
            return null;
        }

        try {
            $ticket = $this->tickets->showAdmin($actor, $ticketId);
        } catch (Throwable) {
            return null;
        }

        return [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'status' => $ticket->status,
            'category' => $ticket->category?->name,
            'description' => Str::limit((string) $ticket->description, 400, ''),
        ];
    }

    private function logUsage(
        ?int $userId,
        string $channel,
        ?string $model,
        int $promptChars,
        ?int $promptTokens,
        ?int $completionTokens,
        int $latencyMs,
        bool $ok,
        ?string $errorCode,
        ?string $ip,
    ): void {
        AiRequestLog::query()->create([
            'user_id' => $userId,
            'channel' => $channel,
            'provider' => (string) config('ai.provider', 'openai'),
            'model' => $model,
            'prompt_chars' => min($promptChars, 65535),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'latency_ms' => min($latencyMs, 65535),
            'ok' => $ok,
            'error_code' => $errorCode,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);

        Log::info('ai.request', [
            'channel' => $channel,
            'user_id' => $userId,
            'model' => $model,
            'prompt_chars' => $promptChars,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'latency_ms' => $latencyMs,
            'ok' => $ok,
            'error_code' => $errorCode,
        ]);
    }
}
