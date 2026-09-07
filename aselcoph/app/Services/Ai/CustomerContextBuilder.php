<?php

namespace App\Services\Ai;

use App\Models\AccountLink;
use App\Models\BillingUpload;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\AstWalletService;
use Illuminate\Support\Str;

/**
 * Builds authenticated, ownership-scoped context for the AI assistant.
 * Never exposes full account numbers or payment credentials to prompts/logs.
 */
class CustomerContextBuilder
{
    public function __construct(private AstWalletService $wallets)
    {
    }

    /**
     * @return array{
     *   billing: array<string, mixed>|null,
     *   tickets: list<array<string, mixed>>,
     *   categories: list<array<string, mixed>>,
     *   prompt_block: string
     * }
     */
    public function forAssistant(User $user, string $message, bool $includeBillingDetail = false): array
    {
        $billing = $includeBillingDetail || $this->looksBillingRelated($message)
            ? $this->ownedBillingSnapshot($user)
            : null;

        $tickets = $this->ownedOpenTickets($user);
        $categories = $this->complaintCategories();

        return [
            'billing' => $billing,
            'tickets' => $tickets,
            'categories' => $categories,
            'prompt_block' => $this->toPromptBlock($user, $billing, $tickets, $categories),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ownedBillingSnapshot(User $user): array
    {
        $validatedLinks = AccountLink::query()
            ->where('user_id', $user->id)
            ->whereNotNull('validated_at')
            ->orderByDesc('validated_at')
            ->get();

        $pending = BillingUpload::query()
            ->whereHas('accountLink', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', [
                BillingUpload::STATUS_PENDING,
                BillingUpload::STATUS_PARTIALLY_PAID,
                'Pending',
            ])
            ->get();

        $amountDue = (float) $pending->sum(fn (BillingUpload $b) => (float) ($b->balance_due ?? $b->amount));

        $wallet = $this->wallets->summaryForUser($user->id);

        return [
            'validated_accounts' => $validatedLinks->count(),
            'masked_accounts' => $validatedLinks->take(3)->map(fn (AccountLink $link) => [
                'masked' => $this->maskAccount((string) $link->account_number),
                'status' => 'validated',
            ])->values()->all(),
            'pending_bill_count' => $pending->count(),
            'amount_due' => round($amountDue, 2),
            'has_billing_data' => $pending->isNotEmpty() || $validatedLinks->isNotEmpty(),
            'ast_balance' => isset($wallet['balance']) ? (float) $wallet['balance'] : null,
            'unit' => $wallet['unit'] ?? 'AST',
            'can_pay_in_app' => $validatedLinks->isNotEmpty(),
            'note' => 'AI cannot change bills, load AST, or process payments. Guide to Pay / Report a Concern.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ownedOpenTickets(User $user): array
    {
        return Ticket::query()
            ->where('customer_id', $user->id)
            ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])
            ->with('category:id,name,department_code')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'status' => $ticket->status,
                'category' => $ticket->category?->name,
                'department' => $ticket->category?->department_code,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function complaintCategories(): array
    {
        return TicketCategory::query()
            ->orderBy('id')
            ->get(['id', 'name', 'department_code', 'sla_minutes'])
            ->map(fn (TicketCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'department_code' => $category->department_code,
                'sla_minutes' => $category->sla_minutes,
            ])
            ->all();
    }

    public function resolveCategoryId(?string $hint, ?int $categoryId = null): ?int
    {
        if ($categoryId) {
            $exists = TicketCategory::query()->whereKey($categoryId)->exists();

            return $exists ? $categoryId : null;
        }

        if (! $hint) {
            return TicketCategory::query()->where('department_code', 'FOCAL')->value('id');
        }

        $byDept = TicketCategory::query()->where('department_code', $hint)->value('id');
        if ($byDept) {
            return (int) $byDept;
        }

        // Outage often maps to Distribution Line (COMD) rather than TSD transmission.
        if ($hint === 'TSD') {
            $comd = TicketCategory::query()->where('department_code', 'COMD')->value('id');
            if ($comd) {
                return (int) $comd;
            }
        }

        return TicketCategory::query()->where('department_code', 'FOCAL')->value('id');
    }

    public function looksBillingRelated(string $message): bool
    {
        $p = mb_strtolower($message);

        foreach (['bill', 'billing', 'amount due', 'ast', 'token', 'pay', 'payment', 'overcharge', 'kwh', 'meter reading'] as $needle) {
            if (str_contains($p, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function looksTicketStatusRelated(string $message): bool
    {
        $p = mb_strtolower($message);

        foreach (['ticket', 'my concern', 'status of', 'case number', 'complaint status', 'follow up'] as $needle) {
            if (str_contains($p, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function maskAccount(string $account): string
    {
        $digits = preg_replace('/\D+/', '', $account) ?: $account;
        if (strlen($digits) <= 4) {
            return '****';
        }

        return '****'.substr($digits, -4);
    }

    /**
     * @param  array<string, mixed>|null  $billing
     * @param  list<array<string, mixed>>  $tickets
     * @param  list<array<string, mixed>>  $categories
     */
    private function toPromptBlock(User $user, ?array $billing, array $tickets, array $categories): string
    {
        $lines = [
            'OWNED CUSTOMER CONTEXT (authenticated; do not invent beyond this):',
            '- first_name: '.Str::of($user->name)->before(' ')->limit(40, ''),
            '- email_verified: '.($user->hasVerifiedEmail() ? 'yes' : 'no'),
        ];

        if ($billing) {
            $lines[] = '- billing.amount_due: '.($billing['amount_due'] ?? 0);
            $lines[] = '- billing.pending_bill_count: '.($billing['pending_bill_count'] ?? 0);
            $lines[] = '- billing.validated_accounts: '.($billing['validated_accounts'] ?? 0);
            $lines[] = '- billing.ast_balance: '.($billing['ast_balance'] ?? 'n/a');
            $lines[] = '- billing.can_pay_in_app: '.(($billing['can_pay_in_app'] ?? false) ? 'yes' : 'no');
            $lines[] = '- billing.rule: never modify amounts; guide to Pay or file Billing & Collection concern';
        } else {
            $lines[] = '- billing: not loaded for this turn (ask general guidance only unless they ask about their bill)';
        }

        if ($tickets === []) {
            $lines[] = '- open_tickets: none';
        } else {
            $lines[] = '- open_tickets:';
            foreach ($tickets as $ticket) {
                $lines[] = '  - '.$ticket['ticket_no'].' status='.$ticket['status'].' category='.($ticket['category'] ?? 'n/a');
            }
            $lines[] = '- ticket_rule: explain status only; never change ticket state';
        }

        if ($categories !== []) {
            $lines[] = '- complaint_categories (guide into Report a Concern; do not invent categories):';
            foreach ($categories as $category) {
                $lines[] = '  - id='.$category['id'].' ['.$category['department_code'].'] '.$category['name'];
            }
        }

        return implode("\n", $lines);
    }
}
