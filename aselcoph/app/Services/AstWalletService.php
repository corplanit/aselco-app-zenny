<?php

namespace App\Services;

use App\Exceptions\AstAccountForbiddenException;
use App\Events\WalletLoadApprovalRequested;
use App\Events\WalletPaymentMade;
use App\Exceptions\InsufficientAstBalanceException;
use App\Models\AccountLink;
use App\Models\AstAuditLog;
use App\Models\AstLedgerEntry;
use App\Models\AstWallet;
use App\Models\WalletLoadRequest;
use App\Models\BillingUpload;
use App\Models\TAccountRaw;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AstWalletService
{
    public const UNIT = 'AST';

    /**
     * @var list<string>
     */
    public const STAFF_ROLES = [
        'Administrator',
        'administrator',
        'Customer Service',
        'staff',
        'support',
    ];

    public function __construct(private NotificationDispatchService $notifications)
    {
    }

    public function isStaff(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return app(\App\Services\Access\AccessService::class)->isSupport($user)
            || in_array((string) ($user->role ?? ''), self::STAFF_ROLES, true);
    }

    /**
     * @return list<string>
     */
    public function allowedAccountNumbers(int $userId): array
    {
        $fromLinks = AccountLink::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->pluck('account_number')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $fromRaw = collect();
        try {
            $fromRaw = TAccountRaw::query()
                ->where('user_id', $userId)
                ->pluck('account_no')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();
        } catch (Throwable) {
            // Raw ledger table may be unavailable.
        }

        // Prefer portal account links first so ordering stays stable; append raw-only accounts.
        return $fromLinks->merge($fromRaw)->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(int $userId, ?string $primaryAccount = null): array
    {
        $accounts = $this->allowedAccountNumbers($userId);
        $wallets = $accounts === []
            ? collect()
            : AstWallet::query()->whereIn('account_number', $accounts)->get()->keyBy('account_number');

        $rows = [];
        $totalBalance = 0.0;
        foreach ($accounts as $accountNumber) {
            $wallet = $wallets->get($accountNumber);
            $balance = $this->toFloat($wallet?->balance ?? '0.00');
            $totalBalance += $balance;
            $rows[] = [
                'account_number' => $accountNumber,
                'balance' => $balance,
            ];
        }

        // Explicit account (e.g. /wallet?account_number=) always wins.
        // Otherwise prefer the funded account so multi-link customers see real AST.
        $requested = $primaryAccount && in_array($primaryAccount, $accounts, true)
            ? $primaryAccount
            : null;

        $primary = $requested;
        if ($primary === null) {
            $bestBalance = -1.0;
            foreach ($rows as $row) {
                if ($row['balance'] > $bestBalance) {
                    $bestBalance = $row['balance'];
                    $primary = $row['account_number'];
                }
            }
            $primary ??= $accounts[0] ?? null;
        }

        $primaryBalance = 0.0;
        foreach ($rows as $row) {
            if ($row['account_number'] === $primary) {
                $primaryBalance = $row['balance'];
                break;
            }
        }

        return [
            'account_number' => $primary,
            'balance' => $primaryBalance,
            'total_balance' => round($totalBalance, 2),
            'unit' => self::UNIT,
            'accounts' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transactionsForUser(int $userId, ?string $accountNumber = null, int $limit = 50): array
    {
        $allowed = $this->allowedAccountNumbers($userId);
        if ($allowed === []) {
            return [];
        }

        if ($accountNumber !== null && $accountNumber !== '') {
            if (! in_array($accountNumber, $allowed, true)) {
                throw new AstAccountForbiddenException($accountNumber);
            }
            $allowed = [$accountNumber];
        }

        $walletIds = AstWallet::query()
            ->whereIn('account_number', $allowed)
            ->pluck('id');

        if ($walletIds->isEmpty()) {
            return [];
        }

        return AstLedgerEntry::query()
            ->with('wallet:id,account_number')
            ->whereIn('wallet_id', $walletIds)
            ->orderByDesc('id')
            ->limit(min(100, max(1, $limit)))
            ->get()
            ->map(fn (AstLedgerEntry $entry) => $this->entryPayload($entry))
            ->all();
    }

    public function paginatedTransactionsForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        $allowed = $this->allowedAccountNumbers($userId);
        if ($allowed === []) {
            return AstLedgerEntry::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $walletIds = AstWallet::query()
            ->whereIn('account_number', $allowed)
            ->pluck('id');

        return AstLedgerEntry::query()
            ->with(['wallet:id,account_number', 'billingUpload:id,account_link_id,amount,balance_due,status'])
            ->whereIn('wallet_id', $walletIds)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, $perPage)));
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null, meta?: array<string, mixed>}  $context
     */
    public function load(User $staff, string $accountNumber, string $amount, string $idempotencyKey, array $context = []): AstLedgerEntry
    {
        if (! $this->isStaff($staff)) {
            abort(403, 'Only staff can load AST.');
        }

        $accountNumber = $this->normalizeAccount($accountNumber);
        $amount = $this->normalizeAmount($amount);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        $entry = $this->mutateWallet(
            $accountNumber,
            $staff,
            $idempotencyKey,
            function (AstWallet $wallet, string $balanceBefore) use ($amount, $staff): array {
                $balanceAfter = $this->add($balanceBefore, $amount);

                return [
                    'type' => AstLedgerEntry::TYPE_LOAD,
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'cis_status' => AstLedgerEntry::CIS_NOT_APPLICABLE,
                    'created_by' => $staff->id,
                    'meta' => ['reason' => 'admin_load'],
                    'notify_user_id' => $wallet->user_id,
                    'notify_title' => 'AST loaded',
                    'notify_body' => $amount.' AST was added to account '.$wallet->account_number.'.',
                ];
            },
            $context,
            'load'
        );

        return $entry;
    }

    /**
     * Support (or any staff) queues a load for a cashier / wallet loader to approve.
     *
     * @param  array{ip?: string|null, user_agent?: string|null, meta?: array<string, mixed>}  $context
     */
    public function requestLoad(
        User $staff,
        string $accountNumber,
        string $amount,
        string $idempotencyKey,
        array $context = []
    ): WalletLoadRequest {
        if (! $this->isStaff($staff)) {
            abort(403, 'Only staff can request an AST load.');
        }

        $accountNumber = $this->normalizeAccount($accountNumber);
        $amount = $this->normalizeAmount($amount);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $remarks = trim((string) data_get($context, 'meta.remarks', ''));
        $referenceNo = trim((string) data_get($context, 'meta.reference_no', ''));

        if (mb_strlen($remarks) < 5) {
            throw new InvalidArgumentException('A reason of at least 5 characters is required.');
        }

        $max = (float) config('ast.max_load_amount', 100000);
        if ((float) $amount > $max) {
            throw new InvalidArgumentException('Amount cannot exceed '.number_format($max, 2).' AST.');
        }

        $customerId = $this->resolveLinkedUserId($accountNumber);
        if ($customerId === null) {
            throw new InvalidArgumentException('This account is not linked to a customer app user.');
        }

        $existing = WalletLoadRequest::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $pending = WalletLoadRequest::query()
            ->where('account_number', $accountNumber)
            ->where('status', WalletLoadRequest::STATUS_PENDING)
            ->first();
        if ($pending) {
            throw new InvalidArgumentException(
                'Account '.$accountNumber.' already has a pending load request ('.$pending->reference_no.').'
            );
        }

        if ($referenceNo === '') {
            $referenceNo = $this->nextRequestReference();
        } elseif (
            WalletLoadRequest::query()->where('reference_no', $referenceNo)->exists()
            || AstLedgerEntry::query()->where('reference', $referenceNo)->exists()
        ) {
            throw new InvalidArgumentException('That reference number has already been used.');
        }

        $request = WalletLoadRequest::query()->create([
            'customer_id' => $customerId,
            'account_number' => $accountNumber,
            'admin_id' => $staff->id,
            'amount' => $amount,
            'reference_no' => $referenceNo,
            'idempotency_key' => $idempotencyKey,
            'status' => WalletLoadRequest::STATUS_PENDING,
            'source' => WalletLoadRequest::SOURCE_SUPPORT,
            'remarks' => $remarks,
        ]);

        WalletLoadApprovalRequested::dispatch($request, $staff);

        return $request;
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null, meta?: array<string, mixed>}  $context
     */
    public function approveLoadRequest(User $checker, int $requestId, array $context = []): AstLedgerEntry
    {
        if (! $checker->canLoadWallet()) {
            abort(403, 'Your role does not have permission to load AST.');
        }

        return DB::transaction(function () use ($checker, $requestId, $context) {
            /** @var WalletLoadRequest $request */
            $request = WalletLoadRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status === WalletLoadRequest::STATUS_COMPLETED) {
                $entry = AstLedgerEntry::query()->where('idempotency_key', $request->idempotency_key)->first();
                if ($entry) {
                    return $entry;
                }
            }

            if ($request->status !== WalletLoadRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('This load request is no longer pending.');
            }

            if ((int) $request->admin_id === (int) $checker->id) {
                throw new InvalidArgumentException('A different staff member must approve this load.');
            }

            $accountNumber = $this->normalizeAccount((string) ($request->account_number ?: ''));
            $meta = array_merge($context['meta'] ?? [], [
                'source' => 'support_request',
                'load_request_id' => $request->id,
                'reference_no' => $request->reference_no,
                'remarks' => $request->remarks,
            ]);

            $entry = $this->load(
                $checker,
                $accountNumber,
                $this->normalizeAmount((string) $request->amount),
                $request->idempotency_key,
                [
                    'ip' => $context['ip'] ?? null,
                    'user_agent' => $context['user_agent'] ?? null,
                    'meta' => $meta,
                ]
            );

            $request->status = WalletLoadRequest::STATUS_COMPLETED;
            $request->approved_by = $checker->id;
            $request->approved_at = now();
            $request->save();

            return $entry;
        });
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     */
    public function rejectLoadRequest(User $checker, int $requestId, string $reason, array $context = []): WalletLoadRequest
    {
        if (! $checker->canLoadWallet()) {
            abort(403, 'Your role does not have permission to reject AST load requests.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $request = DB::transaction(function () use ($checker, $requestId, $reason) {
            /** @var WalletLoadRequest $request */
            $request = WalletLoadRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status !== WalletLoadRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('This load request is no longer pending.');
            }

            if ((int) $request->admin_id === (int) $checker->id) {
                throw new InvalidArgumentException('A different staff member must reject this load.');
            }

            $request->status = WalletLoadRequest::STATUS_REJECTED;
            $request->approved_by = $checker->id;
            $request->approved_at = now();
            $request->remarks = trim($request->remarks ? $request->remarks.' | '.$reason : $reason);
            $request->save();

            return $request;
        });

        $amount = number_format((float) $request->amount, 2);
        $account = (string) $request->account_number;
        $title = 'AST load request rejected';
        $body = 'Load of '.$amount.' AST for account '.$account.' was rejected. Reason: '.$reason;

        if ($request->customer_id) {
            $this->notifications->notifyBilling((int) $request->customer_id, $title, $body, [
                'load_request_id' => (string) $request->id,
                'account_number' => $account,
                'deep_link' => '/tabs/ledger',
            ]);
        }

        if ($request->admin_id && (int) $request->admin_id !== (int) $checker->id) {
            $this->notifications->notifyAlert((int) $request->admin_id, $title, $body, [
                'load_request_id' => (string) $request->id,
                'account_number' => $account,
            ]);
        }

        return $request;
    }

    /**
     * Staff correction: reduce, increase, or set an exact AST balance.
     *
     * @param  array{ip?: string|null, user_agent?: string|null, meta?: array<string, mixed>}  $context
     */
    public function adjust(
        User $staff,
        string $accountNumber,
        string $amount,
        string $mode,
        string $reason,
        string $idempotencyKey,
        array $context = []
    ): AstLedgerEntry {
        if (! $this->isStaff($staff)) {
            abort(403, 'Only staff can adjust AST.');
        }

        $mode = strtolower(trim($mode));
        if (! in_array($mode, ['debit', 'credit', 'set'], true)) {
            throw new InvalidArgumentException('Choose reduce, increase, or set a new balance.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('A reason of at least 5 characters is required.');
        }

        $accountNumber = $this->normalizeAccount($accountNumber);
        $amount = $this->normalizeAmount($amount, allowZero: $mode === 'set');
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        return $this->mutateWallet(
            $accountNumber,
            $staff,
            $idempotencyKey,
            function (AstWallet $wallet, string $balanceBefore) use ($amount, $mode, $staff, $reason): array {
                if ($mode === 'set') {
                    $cmp = $this->compare($amount, $balanceBefore);
                    if ($cmp === 0) {
                        throw new InvalidArgumentException('The new balance is the same as the current balance.');
                    }
                    $direction = $cmp > 0 ? 'credit' : 'debit';
                    $delta = $direction === 'credit'
                        ? $this->subtract($amount, $balanceBefore)
                        : $this->subtract($balanceBefore, $amount);
                    $balanceAfter = $amount;
                } elseif ($mode === 'credit') {
                    $direction = 'credit';
                    $delta = $amount;
                    $balanceAfter = $this->add($balanceBefore, $amount);
                } else {
                    $direction = 'debit';
                    $delta = $amount;
                    if ($this->compare($balanceBefore, $amount) < 0) {
                        throw new InsufficientAstBalanceException($balanceBefore, $amount);
                    }
                    $balanceAfter = $this->subtract($balanceBefore, $amount);
                }

                if ($this->compare($balanceAfter, '0.00') < 0) {
                    throw new InsufficientAstBalanceException($balanceBefore, $delta);
                }

                $verb = $direction === 'credit' ? 'added to' : 'removed from';

                return [
                    'type' => AstLedgerEntry::TYPE_ADJUST,
                    'amount' => $delta,
                    'balance_after' => $balanceAfter,
                    'cis_status' => AstLedgerEntry::CIS_NOT_APPLICABLE,
                    'created_by' => $staff->id,
                    'meta' => [
                        'reason' => $reason,
                        'direction' => $direction,
                        'mode' => $mode,
                        'source' => 'admin_adjust',
                    ],
                    'notify_user_id' => $wallet->user_id,
                    'notify_title' => 'AST balance adjusted',
                    'notify_body' => $delta.' AST was '.$verb.' account '.$wallet->account_number.'.',
                ];
            },
            $context,
            'adjust'
        );
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null, meta?: array<string, mixed>}  $context
     */
    public function pay(User $member, string $accountNumber, string $amount, string $idempotencyKey, array $context = []): AstLedgerEntry
    {
        $accountNumber = $this->normalizeAccount($accountNumber);
        $allowed = $this->allowedAccountNumbers($member->id);
        if (! in_array($accountNumber, $allowed, true)) {
            throw new AstAccountForbiddenException($accountNumber);
        }

        $amount = $this->normalizeAmount($amount);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        $entry = $this->mutateWallet(
            $accountNumber,
            $member,
            $idempotencyKey,
            function (AstWallet $wallet, string $balanceBefore) use ($amount, $member): array {
                if ($this->compare($balanceBefore, $amount) < 0) {
                    throw new InsufficientAstBalanceException($balanceBefore, $amount);
                }

                $balanceAfter = $this->subtract($balanceBefore, $amount);

                return [
                    'type' => AstLedgerEntry::TYPE_PAY,
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'cis_status' => AstLedgerEntry::CIS_PENDING_POST,
                    'created_by' => $member->id,
                    'meta' => ['settlement' => 'pending_cis_post'],
                    'notify_via_event' => true,
                    'notify_user_id' => $member->id,
                    'notify_title' => 'Bill paid with AST',
                    'notify_body' => $amount.' AST was deducted from account '.$wallet->account_number.'. Staff will post this payment to the official ledger.',
                ];
            },
            $context,
            'pay',
            $member->id
        );

        if ($entry->wasRecentlyCreated) {
            WalletPaymentMade::dispatch($member, $entry, $context);
        }

        return $entry;
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null, meta?: array<string, mixed>}  $context
     */
    public function payBill(User $member, int $billingId, string $amount, string $idempotencyKey, array $context = []): AstLedgerEntry
    {
        $amount = $this->normalizeAmount($amount);
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        $candidateBill = BillingUpload::query()
            ->with('accountLink')
            ->whereKey($billingId)
            ->whereHas('accountLink', fn ($query) => $query->where('user_id', $member->id))
            ->first();

        if (! $candidateBill || ! $candidateBill->accountLink) {
            throw new InvalidArgumentException('Bill not found for this customer.');
        }

        try {
            $entry = DB::transaction(function () use ($member, $billingId, $candidateBill, $amount, $idempotencyKey, $context) {
                $existing = AstLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }

                $accountNumber = $this->normalizeAccount((string) $candidateBill->accountLink->account_number);
                $wallet = $this->lockWallet($accountNumber, $member->id);
                $balanceBefore = $this->normalizeAmount((string) $wallet->balance, allowZero: true);

                /** @var BillingUpload|null $bill */
                $bill = BillingUpload::query()
                    ->whereKey($billingId)
                    ->whereHas('accountLink', fn ($query) => $query->where('user_id', $member->id))
                    ->lockForUpdate()
                    ->first();

                if (! $bill) {
                    throw new InvalidArgumentException('Bill not found for this customer.');
                }

                $bill->loadMissing('accountLink');

                $balanceDue = $this->normalizeAmount((string) ($bill->balance_due ?? $bill->amount), allowZero: true);
                $paidAmount = $this->normalizeAmount((string) ($bill->paid_amount ?? '0.00'), allowZero: true);

                if ($this->compare($balanceDue, '0.00') <= 0 || $bill->status === BillingUpload::STATUS_PAID) {
                    throw new InvalidArgumentException('This bill has already been fully paid.');
                }

                if ($this->compare($amount, $balanceDue) > 0) {
                    throw new InvalidArgumentException('Payment amount exceeds the remaining balance due.');
                }

                if ($this->compare($balanceBefore, $amount) < 0) {
                    throw new InsufficientAstBalanceException($balanceBefore, $amount);
                }

                $balanceAfter = $this->subtract($balanceBefore, $amount);
                $newPaidAmount = $this->add($paidAmount, $amount);
                $newBalanceDue = $this->subtract($balanceDue, $amount);

                $wallet->setAttribute('balance', $balanceAfter);
                if ($wallet->user_id === null) {
                    $wallet->user_id = $member->id;
                }
                $wallet->save();

                $entry = AstLedgerEntry::query()->create([
                    'wallet_id' => $wallet->id,
                    'billing_upload_id' => $bill->id,
                    'type' => AstLedgerEntry::TYPE_PAY,
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'idempotency_key' => $idempotencyKey,
                    'reference' => $this->nextReference(),
                    'created_by' => $member->id,
                    'cis_status' => AstLedgerEntry::CIS_PENDING_POST,
                    'meta' => array_merge($context['meta'] ?? [], [
                        'settlement' => 'pending_cis_post',
                        'billing_upload_id' => $bill->id,
                        'account_number' => $accountNumber,
                        'paid_amount' => $amount,
                        'bill_balance_due_after' => $newBalanceDue,
                    ]),
                ]);

                $bill->setAttribute('paid_amount', $newPaidAmount);
                $bill->setAttribute('balance_due', $newBalanceDue);
                $bill->status = $this->compare($newBalanceDue, '0.00') === 0
                    ? BillingUpload::STATUS_PAID
                    : BillingUpload::STATUS_PARTIALLY_PAID;
                $bill->last_payment_reference = $entry->reference;
                $bill->save();

                $this->writeAudit(
                    $wallet,
                    $entry,
                    $member,
                    'pay',
                    $amount,
                    $balanceBefore,
                    $balanceAfter,
                    $idempotencyKey,
                    $context,
                    [
                        'billing_upload_id' => $bill->id,
                        'bill_balance_due_before' => $balanceDue,
                        'bill_balance_due_after' => $newBalanceDue,
                    ]
                );

                DB::afterCommit(function () use ($member, $amount, $wallet, $entry, $bill, $newBalanceDue) {
                    $this->notifications->notifyBilling($member->id, 'Bill paid with AST', $amount.' AST was applied to your bill. Remaining bill balance: '.$newBalanceDue.'.', [
                        'deep_link' => '/tabs/pay',
                        'ledger_entry_id' => (string) $entry->id,
                        'account_number' => $wallet->account_number,
                        'billing_upload_id' => (string) $bill->id,
                        'reference' => $entry->reference,
                    ]);
                });

                return $entry;
            });

            if ($entry->wasRecentlyCreated) {
                WalletPaymentMade::dispatch($member, $entry, $context);
            }

            return $entry;
        } catch (UniqueConstraintViolationException) {
            $replay = AstLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($replay) {
                return $replay;
            }

            throw new InvalidArgumentException('Duplicate AST operation could not be replayed.');
        }
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     */
    public function postToCis(User $staff, int $entryId, string $externalRef, array $context = []): AstLedgerEntry
    {
        if (! $this->isStaff($staff)) {
            abort(403, 'Only staff can post AST payments to CIS.');
        }

        $externalRef = trim($externalRef);
        if ($externalRef === '') {
            throw new InvalidArgumentException('A CIS reference is required.');
        }

        return DB::transaction(function () use ($staff, $entryId, $externalRef, $context) {
            /** @var AstLedgerEntry $entry */
            $entry = AstLedgerEntry::query()->whereKey($entryId)->lockForUpdate()->firstOrFail();

            $wallet = AstWallet::query()
                ->whereKey($entry->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->type !== AstLedgerEntry::TYPE_PAY) {
                abort(422, 'Only AST bill payments can be posted to CIS.');
            }

            if ($entry->cis_status === AstLedgerEntry::CIS_POSTED) {
                return $entry->fresh() ?? $entry;
            }

            $balance = $this->normalizeAmount((string) $wallet->balance, allowZero: true);

            $entry->cis_status = AstLedgerEntry::CIS_POSTED;
            $entry->cis_posted_at = now();
            $entry->cis_posted_by = $staff->id;
            $entry->cis_external_ref = $externalRef;
            $entry->save();

            $this->writeAudit(
                $wallet,
                $entry,
                $staff,
                'post_cis',
                (string) $entry->amount,
                $balance,
                $balance,
                (string) $entry->idempotency_key,
                $context,
                ['cis_external_ref' => $externalRef]
            );

            return $entry->fresh() ?? $entry;
        });
    }

    /**
     * @param  callable(AstWallet, string): array<string, mixed>  $mutator
     * @param  array{ip?: string|null, user_agent?: string|null, meta?: array<string, mixed>}  $context
     */
    private function mutateWallet(
        string $accountNumber,
        User $actor,
        string $idempotencyKey,
        callable $mutator,
        array $context,
        string $action,
        ?int $bindUserId = null
    ): AstLedgerEntry {
        try {
            return DB::transaction(function () use ($accountNumber, $actor, $idempotencyKey, $mutator, $context, $action, $bindUserId) {
                $existing = AstLedgerEntry::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }

                $wallet = $this->lockWallet($accountNumber, $bindUserId ?? $this->resolveLinkedUserId($accountNumber));
                $balanceBefore = $this->normalizeAmount((string) $wallet->balance, allowZero: true);

                $payload = $mutator($wallet, $balanceBefore);

                $wallet->balance = $payload['balance_after'];
                if ($wallet->user_id === null && $bindUserId !== null) {
                    $wallet->user_id = $bindUserId;
                }
                $wallet->save();

                $entry = AstLedgerEntry::query()->create([
                    'wallet_id' => $wallet->id,
                    'type' => $payload['type'],
                    'amount' => $payload['amount'],
                    'balance_after' => $payload['balance_after'],
                    'idempotency_key' => $idempotencyKey,
                    'reference' => $this->nextReference(),
                    'created_by' => $payload['created_by'],
                    'cis_status' => $payload['cis_status'],
                    'meta' => array_merge($context['meta'] ?? [], $payload['meta'] ?? []),
                ]);

                $this->writeAudit(
                    $wallet,
                    $entry,
                    $actor,
                    $action,
                    (string) $payload['amount'],
                    $balanceBefore,
                    (string) $payload['balance_after'],
                    $idempotencyKey,
                    $context
                );

                $notifyUserId = $payload['notify_user_id'] ?? null;
                $notifyTitle = $payload['notify_title'] ?? null;
                $notifyBody = $payload['notify_body'] ?? null;
                if (($payload['notify_via_event'] ?? false) !== true && $notifyUserId && $notifyTitle && $notifyBody) {
                    $entryId = $entry->id;
                    $account = $wallet->account_number;
                    DB::afterCommit(function () use ($notifyUserId, $notifyTitle, $notifyBody, $entryId, $account) {
                        $this->notifications->notifyBilling($notifyUserId, $notifyTitle, $notifyBody, [
                            'deep_link' => '/tabs/pay',
                            'ledger_entry_id' => (string) $entryId,
                            'account_number' => $account,
                        ]);
                    });
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            $replay = AstLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($replay) {
                return $replay;
            }

            throw new InvalidArgumentException('Duplicate AST operation could not be replayed.');
        }
    }

    private function lockWallet(string $accountNumber, ?int $userId = null): AstWallet
    {
        $wallet = AstWallet::query()
            ->where('account_number', $accountNumber)
            ->lockForUpdate()
            ->first();

        if ($wallet) {
            if ($wallet->user_id === null && $userId !== null) {
                $wallet->user_id = $userId;
                $wallet->save();
            }

            return $wallet;
        }

        try {
            $created = AstWallet::query()->create([
                'account_number' => $accountNumber,
                'user_id' => $userId,
                'balance' => '0.00',
            ]);
        } catch (UniqueConstraintViolationException) {
            $created = AstWallet::query()->where('account_number', $accountNumber)->firstOrFail();
        }

        return AstWallet::query()
            ->whereKey($created->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     * @param  array<string, mixed>  $meta
     */
    private function writeAudit(
        AstWallet $wallet,
        AstLedgerEntry $entry,
        User $actor,
        string $action,
        string $amount,
        string $balanceBefore,
        string $balanceAfter,
        string $idempotencyKey,
        array $context,
        array $meta = []
    ): void {
        AstAuditLog::query()->create([
            'wallet_id' => $wallet->id,
            'ledger_entry_id' => $entry->id,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role,
            'action' => $action,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'idempotency_key' => $idempotencyKey,
            'ip_address' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    private function resolveLinkedUserId(string $accountNumber): ?int
    {
        $link = AccountLink::query()
            ->where('account_number', $accountNumber)
            ->orderByDesc('validated_at')
            ->orderByDesc('id')
            ->first();
        if ($link) {
            return (int) $link->user_id;
        }

        try {
            $raw = TAccountRaw::query()->where('account_no', $accountNumber)->first();
            if ($raw && $raw->user_id) {
                return (int) $raw->user_id;
            }
        } catch (Throwable) {
            // Raw table may be unavailable.
        }

        return null;
    }

    private function nextReference(): string
    {
        do {
            $reference = 'AST-'.Str::upper(Str::random(8));
        } while (AstLedgerEntry::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function nextRequestReference(): string
    {
        do {
            $reference = 'REQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (WalletLoadRequest::query()->where('reference_no', $reference)->exists());

        return $reference;
    }

    /**
     * @return array<string, mixed>
     */
    public function entryPayload(AstLedgerEntry $entry): array
    {
        $entry->loadMissing('wallet:id,account_number');

        return [
            'id' => $entry->id,
            'account_number' => $entry->wallet?->account_number,
            'billing_upload_id' => $entry->billing_upload_id,
            'type' => $entry->type,
            'amount' => $this->toFloat($entry->amount),
            'balance_after' => $this->toFloat($entry->balance_after),
            'reference' => $entry->reference,
            'cis_status' => $entry->cis_status,
            'cis_external_ref' => $entry->cis_external_ref,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    public function normalizeAccount(string $accountNumber): string
    {
        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            throw new InvalidArgumentException('Account number is required.');
        }

        return $accountNumber;
    }

    public function normalizeAmount(string $amount, bool $allowZero = false): string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a positive AST value with up to 2 decimal places.');
        }

        $normalized = $this->fromCents($this->toCents($amount));
        if (! $allowZero && $this->compare($normalized, '0.00') <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $normalized;
    }

    public function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('A valid idempotency key is required.');
        }

        return $key;
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function add(string $left, string $right): string
    {
        return $this->fromCents($this->toCents($left) + $this->toCents($right));
    }

    private function subtract(string $left, string $right): string
    {
        return $this->fromCents($this->toCents($left) - $this->toCents($right));
    }

    private function compare(string $left, string $right): int
    {
        return $this->toCents($left) <=> $this->toCents($right);
    }

    private function toFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
