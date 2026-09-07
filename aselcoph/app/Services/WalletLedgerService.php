<?php

namespace App\Services;

use App\Events\WalletLoaded;
use App\Events\WalletLoadApprovalRequested;
use App\Events\WalletLoadFailed;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletAuditLog;
use App\Models\WalletLoadRequest;
use App\Models\WalletTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletLedgerService
{
    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     * @return array<string, mixed>
     */
    public function loadByAdmin(
        User $admin,
        int $customerId,
        string $amount,
        string $referenceNo,
        ?string $remarks,
        string $idempotencyKey,
        array $context = []
    ): array {
        $amount = $this->normalizeAmount($amount);
        $referenceNo = $this->normalizeReference($referenceNo);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $remarks = $this->normalizeRemarks($remarks);

        $replay = $this->findReplay($idempotencyKey);
        if ($replay !== null) {
            $this->audit(null, $admin, 'load_duplicate_blocked', $context, [
                'idempotency_key' => $idempotencyKey,
                'reference_no' => $referenceNo,
            ], $replay);

            return $replay + ['replayed' => true];
        }

        $customer = User::query()->find($customerId);
        if ($customer === null || ! $customer->isActiveCustomer()) {
            $this->audit(null, $admin, 'load_failed', $context, [
                'customer_id' => $customerId,
                'reason' => 'inactive_or_missing_customer',
            ]);
            WalletLoadFailed::dispatch($admin, 'inactive_or_missing_customer', ['customer_id' => $customerId]);
            throw new InvalidArgumentException('customer_id must be an active customer account.');
        }

        if (WalletTransaction::query()->where('reference_no', $referenceNo)->exists()
            || WalletLoadRequest::query()->where('reference_no', $referenceNo)->exists()) {
            $this->audit(null, $admin, 'load_failed', $context, [
                'reference_no' => $referenceNo,
                'reason' => 'duplicate_reference_no',
            ]);
            WalletLoadFailed::dispatch($admin, 'duplicate_reference_no', ['reference_no' => $referenceNo]);
            throw new InvalidArgumentException('reference_no has already been used.');
        }

        $threshold = (float) config('ast.load_approval_threshold', 10000);
        if ((float) $amount >= $threshold) {
            return $this->queueLoadRequest($admin, $customer, $amount, $referenceNo, $remarks, $idempotencyKey, $context);
        }

        return $this->creditWallet($admin, $customer, $amount, $referenceNo, $remarks, $idempotencyKey, $context);
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     * @return array<string, mixed>
     */
    public function approveLoadRequest(User $checker, int $requestId, array $context = []): array
    {
        return DB::transaction(function () use ($checker, $requestId, $context) {
            /** @var WalletLoadRequest $request */
            $request = WalletLoadRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status === WalletLoadRequest::STATUS_COMPLETED) {
                $txn = WalletTransaction::query()->where('idempotency_key', $request->idempotency_key)->first();
                if ($txn) {
                    $this->audit($txn->wallet_id, $checker, 'load_duplicate_blocked', $context, [
                        'load_request_id' => $request->id,
                    ]);

                    return $this->transactionPayload($txn, (float) $txn->balance_after) + ['replayed' => true];
                }
            }

            if ($request->status !== WalletLoadRequest::STATUS_PENDING) {
                $this->audit(null, $checker, 'load_failed', $context, [
                    'load_request_id' => $request->id,
                    'reason' => 'not_pending',
                    'status' => $request->status,
                ]);
                throw new InvalidArgumentException('This load request is no longer pending.');
            }

            if ((int) $request->admin_id === (int) $checker->id) {
                $this->audit(null, $checker, 'load_failed', $context, [
                    'load_request_id' => $request->id,
                    'reason' => 'maker_cannot_approve',
                ]);
                throw new InvalidArgumentException('A different administrator must approve this load.');
            }

            $customer = User::query()->findOrFail($request->customer_id);
            $result = $this->creditWallet(
                $checker,
                $customer,
                $this->normalizeAmount((string) $request->amount),
                $request->reference_no,
                $request->remarks,
                $request->idempotency_key,
                $context,
                $request
            );

            $request->status = WalletLoadRequest::STATUS_COMPLETED;
            $request->approved_by = $checker->id;
            $request->approved_at = now();
            $request->save();

            return $result;
        });
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     * @return array<string, mixed>
     */
    public function rejectLoadRequest(User $checker, int $requestId, ?string $remarks, array $context = []): array
    {
        return DB::transaction(function () use ($checker, $requestId, $remarks, $context) {
            /** @var WalletLoadRequest $request */
            $request = WalletLoadRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status !== WalletLoadRequest::STATUS_PENDING) {
                $this->audit(null, $checker, 'load_failed', $context, [
                    'load_request_id' => $request->id,
                    'reason' => 'not_pending',
                ]);
                throw new InvalidArgumentException('This load request is no longer pending.');
            }

            if ((int) $request->admin_id === (int) $checker->id) {
                $this->audit(null, $checker, 'load_failed', $context, [
                    'load_request_id' => $request->id,
                    'reason' => 'maker_cannot_reject',
                ]);
                throw new InvalidArgumentException('A different administrator must reject this load.');
            }

            $request->status = WalletLoadRequest::STATUS_REJECTED;
            $request->approved_by = $checker->id;
            $request->approved_at = now();
            if ($remarks) {
                $request->remarks = trim($request->remarks ? $request->remarks.' | '.$remarks : $remarks);
            }
            $request->save();

            $wallet = Wallet::query()->where('customer_id', $request->customer_id)->first();
            $this->audit($wallet?->id, $checker, 'load_rejected', $context, null, [
                'load_request_id' => $request->id,
                'amount' => $request->amount,
            ]);

            return [
                'reference_no' => $request->reference_no,
                'amount' => (float) $request->amount,
                'new_balance' => $wallet ? (float) $wallet->balance : 0.0,
                'status' => WalletLoadRequest::STATUS_REJECTED,
                'transaction_id' => null,
                'load_request_id' => $request->id,
                'replayed' => false,
            ];
        });
    }

    /**
     * @param  array{from?: string|null, to?: string|null, type?: string|null, per_page?: int}  $filters
     */
    public function paginateTransactions(int $customerId, array $filters)
    {
        $wallet = Wallet::query()->where('customer_id', $customerId)->first();
        if ($wallet === null) {
            return WalletTransaction::query()->whereRaw('1 = 0')->paginate($filters['per_page'] ?? 15);
        }

        $query = $wallet->transactions()->orderByDesc('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function paginateLoadRequests(?string $status = null, int $perPage = 15)
    {
        $query = WalletLoadRequest::query()
            ->with(['customer:id,name,email', 'maker:id,name,email'])
            ->orderByDesc('id');

        $query->where('status', $status ?: WalletLoadRequest::STATUS_PENDING);

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSummary(): array
    {
        $circulation = (float) Wallet::query()->sum('balance');

        $loadedToday = (float) WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_LOAD)
            ->where('status', WalletTransaction::STATUS_COMPLETED)
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        $loadedMonth = (float) WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_LOAD)
            ->where('status', WalletTransaction::STATUS_COMPLETED)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $topCustomers = Wallet::query()
            ->with('customer:id,name,email')
            ->orderByDesc('balance')
            ->limit(5)
            ->get()
            ->map(fn (Wallet $wallet) => [
                'customer_id' => $wallet->customer_id,
                'name' => $wallet->customer?->name,
                'balance' => (float) $wallet->balance,
            ])
            ->all();

        $recent = WalletTransaction::query()
            ->with('wallet:id,customer_id')
            ->where('type', WalletTransaction::TYPE_LOAD)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (WalletTransaction $txn) => [
                'transaction_id' => $txn->id,
                'customer_id' => $txn->wallet?->customer_id,
                'reference_no' => $txn->reference_no,
                'amount' => (float) $txn->amount,
                'status' => $txn->status,
                'created_at' => $txn->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'unit' => 'AST',
            'php_per_ast' => (float) config('ast.php_per_ast', 1),
            'total_in_circulation' => $circulation,
            'loaded_today' => $loadedToday,
            'loaded_this_month' => $loadedMonth,
            'pending_approvals' => WalletLoadRequest::query()->where('status', WalletLoadRequest::STATUS_PENDING)->count(),
            'top_customers' => $topCustomers,
            'recent_loads' => $recent,
        ];
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     * @return array<string, mixed>
     */
    private function queueLoadRequest(
        User $admin,
        User $customer,
        string $amount,
        string $referenceNo,
        ?string $remarks,
        string $idempotencyKey,
        array $context
    ): array {
        try {
            $request = WalletLoadRequest::query()->create([
                'customer_id' => $customer->id,
                'admin_id' => $admin->id,
                'amount' => $amount,
                'reference_no' => $referenceNo,
                'idempotency_key' => $idempotencyKey,
                'status' => WalletLoadRequest::STATUS_PENDING,
                'remarks' => $remarks,
            ]);
        } catch (UniqueConstraintViolationException) {
            $replay = $this->findReplay($idempotencyKey);
            if ($replay) {
                return $replay + ['replayed' => true];
            }
            throw new InvalidArgumentException('reference_no or idempotency key has already been used.');
        }

        $wallet = Wallet::query()->where('customer_id', $customer->id)->first();
        $this->audit($wallet?->id, $admin, 'load_queued', $context, null, [
            'load_request_id' => $request->id,
            'amount' => $amount,
            'reference_no' => $referenceNo,
        ]);

        DB::afterCommit(function () use ($request, $admin): void {
            WalletLoadApprovalRequested::dispatch($request, $admin);
        });

        return [
            'reference_no' => $referenceNo,
            'amount' => (float) $amount,
            'new_balance' => $wallet ? (float) $wallet->balance : 0.0,
            'status' => 'pending_approval',
            'transaction_id' => null,
            'load_request_id' => $request->id,
            'replayed' => false,
        ];
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     * @return array<string, mixed>
     */
    private function creditWallet(
        User $admin,
        User $customer,
        string $amount,
        string $referenceNo,
        ?string $remarks,
        string $idempotencyKey,
        array $context,
        ?WalletLoadRequest $loadRequest = null
    ): array {
        try {
            $result = DB::transaction(function () use ($admin, $customer, $amount, $referenceNo, $remarks, $idempotencyKey, $context, $loadRequest) {
                $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $this->transactionPayload($existing, (float) $existing->balance_after) + ['replayed' => true];
                }

                $wallet = $this->lockOrCreateWallet($customer->id);
                $before = $this->normalizeAmount((string) $wallet->balance, allowZero: true);
                $after = $this->add($before, $amount);

                $wallet->setAttribute('balance', $after);
                $wallet->save();

                $txn = WalletTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'type' => WalletTransaction::TYPE_LOAD,
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reference_no' => $referenceNo,
                    'idempotency_key' => $idempotencyKey,
                    'source' => WalletTransaction::SOURCE_ADMIN,
                    'source_id' => $admin->id,
                    'status' => WalletTransaction::STATUS_COMPLETED,
                    'remarks' => $remarks,
                ]);

                $this->audit($wallet->id, $admin, 'load_completed', $context, [
                    'balance' => $before,
                ], [
                    'balance' => $after,
                    'transaction_id' => $txn->id,
                    'load_request_id' => $loadRequest?->id,
                    'reference_no' => $referenceNo,
                ]);

                DB::afterCommit(function () use ($customer, $txn) {
                    WalletLoaded::dispatch($customer, $txn);
                });

                return $this->transactionPayload($txn, (float) $after) + ['replayed' => false];
            });
        } catch (UniqueConstraintViolationException) {
            $replay = $this->findReplay($idempotencyKey);
            if ($replay) {
                $this->audit(null, $admin, 'load_duplicate_blocked', $context, [
                    'idempotency_key' => $idempotencyKey,
                ], $replay);

                return $replay + ['replayed' => true];
            }
            throw new InvalidArgumentException('reference_no or idempotency key has already been used.');
        }

        return $result;
    }

    private function lockOrCreateWallet(int $customerId): Wallet
    {
        $wallet = Wallet::query()->where('customer_id', $customerId)->lockForUpdate()->first();
        if ($wallet) {
            if (! $wallet->isActive()) {
                throw new InvalidArgumentException('This customer wallet is not active.');
            }

            return $wallet;
        }

        try {
            $created = Wallet::query()->create([
                'customer_id' => $customerId,
                'balance' => '0.00',
                'status' => Wallet::STATUS_ACTIVE,
            ]);
        } catch (UniqueConstraintViolationException) {
            $created = Wallet::query()->where('customer_id', $customerId)->firstOrFail();
        }

        $wallet = Wallet::query()->whereKey($created->id)->lockForUpdate()->firstOrFail();
        if (! $wallet->isActive()) {
            throw new InvalidArgumentException('This customer wallet is not active.');
        }

        return $wallet;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findReplay(string $idempotencyKey): ?array
    {
        $txn = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($txn) {
            return $this->transactionPayload($txn, (float) $txn->balance_after);
        }

        $request = WalletLoadRequest::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($request) {
            $wallet = Wallet::query()->where('customer_id', $request->customer_id)->first();

            return [
                'reference_no' => $request->reference_no,
                'amount' => (float) $request->amount,
                'new_balance' => $wallet ? (float) $wallet->balance : 0.0,
                'status' => $request->status === WalletLoadRequest::STATUS_PENDING
                    ? 'pending_approval'
                    : $request->status,
                'transaction_id' => null,
                'load_request_id' => $request->id,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(WalletTransaction $txn, float $balance): array
    {
        return [
            'reference_no' => $txn->reference_no,
            'amount' => (float) $txn->amount,
            'new_balance' => $balance,
            'status' => $txn->status,
            'transaction_id' => $txn->id,
        ];
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(?int $walletId, User $actor, string $action, array $context, ?array $old = null, ?array $new = null): void
    {
        WalletAuditLog::query()->create([
            'wallet_id' => $walletId,
            'actor_id' => $actor->id,
            'actor_type' => WalletAuditLog::ACTOR_ADMIN,
            'action' => $action,
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ]);
    }

    public function normalizeAmount(string $amount, bool $allowZero = false): string
    {
        $amount = trim($amount);
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        $normalized = $this->fromCents($this->toCents(number_format((float) $amount, 2, '.', '')));
        if (! $allowZero && $this->toCents($normalized) < 100) {
            throw new InvalidArgumentException('Amount must be at least 1 AST.');
        }

        return $normalized;
    }

    public function normalizeReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 40) {
            throw new InvalidArgumentException('A valid reference_no is required.');
        }

        return $reference;
    }

    public function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('Idempotency-Key header is required.');
        }

        return $key;
    }

    private function normalizeRemarks(?string $remarks): ?string
    {
        $remarks = $remarks !== null ? trim($remarks) : null;

        return $remarks === '' ? null : $remarks;
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function add(string $left, string $right): string
    {
        return $this->fromCents($this->toCents($left) + $this->toCents($right));
    }
}
