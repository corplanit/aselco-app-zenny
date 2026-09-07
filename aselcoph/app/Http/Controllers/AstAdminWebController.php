<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientAstBalanceException;
use App\Models\AccountLink;
use App\Models\AstAuditLog;
use App\Models\AstLedgerEntry;
use App\Models\AstWallet;
use App\Models\User;
use App\Models\WalletLoadRequest;
use App\Services\AstWalletService;
use App\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Web (Blade) controller for the admin AST wallet management screens.
 *
 * Role guards:
 *  - isStaff()     → read-only access (dashboard, customer detail, load-requests view)
 *  - canLoadWallet() → write access (load form submit, approve/reject)
 *
 * Routes (all inside auth:sanctum + verified group):
 *
 *  GET  /ast/admin/dashboard
 *  GET  /ast/admin/daily-chart             (AJAX)
 *  GET  /ast/admin/load
 *  POST /ast/admin/load                    (AJAX)
 *  GET  /ast/admin/request
 *  POST /ast/admin/request                 (AJAX)
 *  GET  /ast/admin/customer-search         (AJAX)
 *  GET  /ast/admin/load-requests
 *  POST /ast/admin/load-requests/{id}/approve  (AJAX)
 *  POST /ast/admin/load-requests/{id}/reject   (AJAX)
 *  GET  /ast/admin/customer/{userId}
 */
class AstAdminWebController extends Controller
{
    public function __construct(
        private AstWalletService $astWallets,
        private WalletLedgerService $ledger,
    ) {
    }

    // -----------------------------------------------------------------------
    // 1. Dashboard
    // -----------------------------------------------------------------------

    public function dashboard(Request $request): View
    {
        $this->assertStaff($request);

        $totalCirculation = (float) AstWallet::query()->sum('balance');

        $loadedToday = (float) AstLedgerEntry::query()
            ->where('type', AstLedgerEntry::TYPE_LOAD)
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        $loadedMonth = (float) AstLedgerEntry::query()
            ->where('type', AstLedgerEntry::TYPE_LOAD)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $pendingApprovals = WalletLoadRequest::query()
            ->where('status', WalletLoadRequest::STATUS_PENDING)
            ->count();

        $walletCount = (int) AstWallet::query()->count();
        $activeWalletCount = (int) AstWallet::query()->where('balance', '>', 0)->count();
        $paidToday = (float) AstLedgerEntry::query()
            ->where('type', AstLedgerEntry::TYPE_PAY)
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        $topWallets = AstWallet::query()
            ->with('user:id,name,email,profile_photo_path')
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit(9)
            ->get();

        $recentLoads = AstLedgerEntry::query()
            ->with(['wallet:id,account_number,user_id', 'wallet.user:id,name', 'creator:id,name,role'])
            ->where('type', AstLedgerEntry::TYPE_LOAD)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('pages.staff.ast.dashboard', compact(
            'totalCirculation',
            'loadedToday',
            'loadedMonth',
            'paidToday',
            'pendingApprovals',
            'walletCount',
            'activeWalletCount',
            'topWallets',
            'recentLoads',
        ));
    }

    /** AJAX: 30-day daily load totals for the dashboard chart */
    public function dailyChart(Request $request): JsonResponse
    {
        $this->assertStaff($request);

        $days = collect(range(29, 0))->map(function (int $offset): array {
            $date = now()->subDays($offset)->toDateString();
            $amount = (float) AstLedgerEntry::query()
                ->where('type', AstLedgerEntry::TYPE_LOAD)
                ->whereDate('created_at', $date)
                ->sum('amount');

            return ['date' => $date, 'amount' => $amount];
        });

        return response()->json(['data' => $days]);
    }

    // -----------------------------------------------------------------------
    // 2. Load AST to Customer
    // -----------------------------------------------------------------------

    public function requestForm(Request $request): View
    {
        $this->assertStaff($request);

        $request->query->set('mode', 'request');

        return $this->loadForm($request);
    }

    /**
     * AJAX: support queues a load for a wallet loader to approve.
     */
    public function requestSubmit(Request $request): JsonResponse
    {
        $this->assertStaff($request);

        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'remarks' => ['required', 'string', 'min:5', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $queued = $this->astWallets->requestLoad(
                $request->user(),
                (string) $validated['account_number'],
                $this->astWallets->normalizeAmount((string) $validated['amount']),
                (string) $validated['idempotency_key'],
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta' => [
                        'source' => 'support_blade',
                        'reference_no' => $validated['reference_no'] ?? null,
                        'remarks' => $validated['remarks'],
                    ],
                ]
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $replayed = ! $queued->wasRecentlyCreated;

        return response()->json([
            'message' => $replayed
                ? 'This request was already submitted.'
                : 'Load request submitted. A staff member with Load wallets permission will review it.',
            'idempotent' => $replayed,
            'status' => $queued->status,
            'reference' => $queued->reference_no,
            'amount' => (float) $queued->amount,
            'account_number' => $queued->account_number,
            'load_request_id' => $queued->id,
        ], $replayed ? 200 : 201);
    }

    public function loadForm(Request $request): View
    {
        $formMode = strtolower(trim((string) $request->query('mode', 'load')));
        if (! in_array($formMode, ['load', 'reduce', 'set', 'request'], true)) {
            $formMode = 'load';
        }

        if ($formMode === 'request') {
            $this->assertStaff($request);
        } else {
            $this->assertCanLoad($request);
        }

        $approvalThreshold = (float) config('ast.load_approval_threshold', 10_000);
        $makerCheckerEnabled = $approvalThreshold > 0;
        $presetCustomer = null;
        $presetAccount = trim((string) $request->query('account', ''));
        $presetWallets = collect();
        $presetAccounts = collect();
        $presetAstBalance = 0.0;

        if ($request->filled('customer')) {
            $presetCustomer = User::query()->find($request->integer('customer'));
        }

        if ($presetCustomer) {
            $presetWallets = AstWallet::query()
                ->where('user_id', $presetCustomer->id)
                ->orderBy('account_number')
                ->get();
            $presetAstBalance = (float) $presetWallets->sum('balance');
            $presetAccounts = $presetWallets
                ->map(fn (AstWallet $wallet) => [
                    'account_number' => $wallet->account_number,
                    'balance' => (float) $wallet->balance,
                ])
                ->concat(
                    AccountLink::query()
                        ->where('user_id', $presetCustomer->id)
                        ->orderBy('account_number')
                        ->get(['account_number'])
                        ->map(fn (AccountLink $link) => [
                            'account_number' => $link->account_number,
                            'balance' => (float) ($presetWallets->firstWhere('account_number', $link->account_number)?->balance ?? 0),
                        ])
                )
                ->unique('account_number')
                ->values();

            if ($presetAccount === '') {
                $presetAccount = (string) data_get($presetAccounts->first(), 'account_number', '');
            }
        }

        return view('pages.staff.ast.load', compact(
            'approvalThreshold',
            'makerCheckerEnabled',
            'presetCustomer',
            'presetAccount',
            'presetWallets',
            'presetAccounts',
            'presetAstBalance',
            'formMode',
        ));
    }

    /**
     * AJAX: submit a load. The browser sends a JSON body with an
     * auto-generated idempotency key so double-click is safe.
     */
    public function loadSubmit(Request $request): JsonResponse
    {
        $this->assertCanLoad($request);

        $validated = $request->validate([
            'account_number'  => ['required', 'string', 'max:50'],
            'amount'          => ['required', 'numeric', 'gt:0'],
            'reference_no'    => ['nullable', 'string', 'max:120'],
            'remarks'         => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $entry = $this->astWallets->load(
                $request->user(),
                (string) $validated['account_number'],
                $this->astWallets->normalizeAmount((string) $validated['amount']),
                (string) $validated['idempotency_key'],
                [
                    'ip'         => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta'       => [
                        'source'       => 'admin_blade',
                        'reference_no' => $validated['reference_no'] ?? null,
                        'remarks'      => $validated['remarks'] ?? null,
                    ],
                ]
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'       => $entry->wasRecentlyCreated
                ? 'AST loaded successfully.'
                : 'This load was already recorded (duplicate idempotency key).',
            'idempotent'    => ! $entry->wasRecentlyCreated,
            'reference'     => $entry->reference,
            'amount'        => (float) $entry->amount,
            'balance_after' => (float) $entry->balance_after,
            'account_number' => $entry->wallet?->account_number,
        ], $entry->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * AJAX: reduce, increase, or set an exact AST balance.
     */
    public function adjustSubmit(Request $request): JsonResponse
    {
        $this->assertCanLoad($request);

        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gte:0'],
            'mode' => ['required', 'in:debit,credit,set'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'remarks' => ['required', 'string', 'min:5', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $entry = $this->astWallets->adjust(
                $request->user(),
                (string) $validated['account_number'],
                $this->astWallets->normalizeAmount(
                    (string) $validated['amount'],
                    allowZero: $validated['mode'] === 'set'
                ),
                (string) $validated['mode'],
                (string) $validated['remarks'],
                (string) $validated['idempotency_key'],
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta' => [
                        'source' => 'admin_blade',
                        'reference_no' => $validated['reference_no'] ?? null,
                        'remarks' => $validated['remarks'],
                    ],
                ]
            );
        } catch (InsufficientAstBalanceException $e) {
            return response()->json([
                'message' => 'Not enough AST on this account. Current balance is '.$e->balance.' AST.',
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $direction = is_array($entry->meta) ? ($entry->meta['direction'] ?? null) : null;

        return response()->json([
            'message' => $entry->wasRecentlyCreated
                ? 'AST balance adjusted.'
                : 'This adjustment was already recorded (duplicate idempotency key).',
            'idempotent' => ! $entry->wasRecentlyCreated,
            'reference' => $entry->reference,
            'amount' => (float) $entry->amount,
            'balance_after' => (float) $entry->balance_after,
            'account_number' => $entry->wallet?->account_number,
            'type' => $entry->type,
            'direction' => $direction,
        ], $entry->wasRecentlyCreated ? 201 : 200);
    }

    /** AJAX: search active customers by name / account number */
    public function customerSearch(Request $request): JsonResponse
    {
        $this->assertStaff($request);

        $q = trim((string) $request->query('q', ''));
        $minLength = preg_match('/^\d/', $q) ? 1 : 2;
        if (strlen($q) < $minLength) {
            return response()->json(['data' => []]);
        }

        $defaultPhoto = asset('/user.png');
        $photoUrl = static function (?User $user) use ($defaultPhoto): string {
            if ($user && filled($user->profile_photo_path)) {
                return asset('storage/'.ltrim((string) $user->profile_photo_path, '/'));
            }

            return $defaultPhoto;
        };

        $userSearch = function ($query) use ($q): void {
            $query->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%")
                ->orWhere('contact_no', 'like', "%{$q}%");
        };

        $matchedUserIds = User::query()
            ->where($userSearch)
            ->limit(15)
            ->pluck('id');

        $linkHits = AccountLink::query()
            ->where(function ($query) use ($q, $matchedUserIds) {
                $query->where('account_number', 'like', "%{$q}%")
                    ->orWhere('owner_name', 'like', "%{$q}%");
                if ($matchedUserIds->isNotEmpty()) {
                    $query->orWhereIn('user_id', $matchedUserIds);
                }
            })
            ->with('user:id,name,email,role,profile_photo_path,contact_no')
            ->select('id', 'user_id', 'account_number', 'owner_name')
            ->limit(20)
            ->get();

        $walletHits = AstWallet::query()
            ->where(function ($query) use ($q, $matchedUserIds) {
                $query->where('account_number', 'like', "%{$q}%");
                if ($matchedUserIds->isNotEmpty()) {
                    $query->orWhereIn('user_id', $matchedUserIds);
                }
            })
            ->with('user:id,name,email,role,profile_photo_path,contact_no')
            ->select('id', 'user_id', 'account_number', 'balance')
            ->limit(20)
            ->get();

        $balances = AstWallet::query()
            ->whereIn(
                'account_number',
                $linkHits->pluck('account_number')->merge($walletHits->pluck('account_number'))->unique()->filter()
            )
            ->pluck('balance', 'account_number');

        $needle = mb_strtolower($q);
        $rows = collect();
        foreach ($linkHits as $link) {
            if (! $link->user || $link->user->canLoadWallet()) {
                continue;
            }
            $rows->push([
                'id' => $link->user_id,
                'name' => $link->user->name,
                'email' => $link->user->email,
                'account_number' => $link->account_number,
                'owner_name' => $link->owner_name,
                'balance' => (float) ($balances[$link->account_number] ?? 0),
                'photo' => $photoUrl($link->user),
            ]);
        }
        foreach ($walletHits as $wallet) {
            if (! $wallet->user || $wallet->user->canLoadWallet()) {
                continue;
            }
            $rows->push([
                'id' => $wallet->user_id,
                'name' => $wallet->user->name,
                'email' => $wallet->user->email,
                'account_number' => $wallet->account_number,
                'owner_name' => null,
                'balance' => (float) $wallet->balance,
                'photo' => $photoUrl($wallet->user),
            ]);
        }

        $ranked = $rows
            ->unique(fn (array $row) => $row['id'].'|'.$row['account_number'])
            ->sortBy(function (array $row) use ($needle) {
                $account = mb_strtolower((string) $row['account_number']);
                $name = mb_strtolower((string) $row['name']);
                if ($account === $needle) {
                    return 0;
                }
                if (str_starts_with($account, $needle)) {
                    return 1;
                }
                if (str_starts_with($name, $needle)) {
                    return 2;
                }

                return 3;
            })
            ->take(8)
            ->values();

        return response()->json(['data' => $ranked]);
    }

    // -----------------------------------------------------------------------
    // 3. Load Requests / Maker-Checker Queue
    // -----------------------------------------------------------------------

    public function loadRequests(Request $request): View
    {
        $this->assertStaff($request);

        $list = \App\Support\ListQuery::from(
            $request,
            filterKeys: ['status'],
            sortable: ['id' => 'id', 'created_at' => 'created_at', 'amount' => 'amount'],
            defaultSort: 'id',
            defaultDir: 'desc',
            defaultPerPage: 20,
        );

        $status = (string) ($list['filters']['status'] ?? $request->query('status', WalletLoadRequest::STATUS_PENDING));
        if (! in_array($status, [
            WalletLoadRequest::STATUS_PENDING,
            WalletLoadRequest::STATUS_APPROVED,
            WalletLoadRequest::STATUS_REJECTED,
            WalletLoadRequest::STATUS_COMPLETED,
        ], true)) {
            $status = WalletLoadRequest::STATUS_PENDING;
        }

        $query = WalletLoadRequest::query()
            ->with(['customer:id,name,email', 'maker:id,name,role', 'checker:id,name'])
            ->where('status', $status);

        if ($list['search']) {
            $term = $list['search'];
            $query->where(function ($q) use ($term) {
                $q->where('reference_no', 'like', "%{$term}%")
                    ->orWhere('account_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            });
        }

        $sortCol = match ($list['sort']) {
            'created_at' => 'created_at',
            'amount' => 'amount',
            default => 'id',
        };
        $requests = $query->orderBy($sortCol, $list['dir'])->paginate($list['per_page'])->withQueryString();

        $pendingCount = WalletLoadRequest::query()
            ->where('status', WalletLoadRequest::STATUS_PENDING)
            ->count();

        $approvalThreshold = (float) config('ast.load_approval_threshold', 10_000);
        $makerCheckerEnabled = $approvalThreshold > 0;
        $filters = ['search' => $list['search'], 'status' => $status, 'sort' => $list['sort'], 'dir' => $list['dir']];
        $activeFilterCount = ($list['search'] ? 1 : 0) + ($status !== WalletLoadRequest::STATUS_PENDING ? 1 : 0);

        return view('pages.staff.ast.load-requests', compact(
            'requests',
            'status',
            'pendingCount',
            'approvalThreshold',
            'makerCheckerEnabled',
            'list',
            'filters',
            'activeFilterCount',
        ));
    }

    /** AJAX: approve a pending load request (must be a different staff from the maker) */
    public function approveRequest(Request $request, int $id): JsonResponse
    {
        $this->assertCanLoad($request);

        $queued = WalletLoadRequest::query()->findOrFail($id);

        try {
            if (filled($queued->account_number) || $queued->source === WalletLoadRequest::SOURCE_SUPPORT) {
                $entry = $this->astWallets->approveLoadRequest(
                    $request->user(),
                    $id,
                    ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
                );

                return response()->json([
                    'message' => 'Load request approved and AST credited.',
                    'replayed' => ! $entry->wasRecentlyCreated,
                    'amount' => (float) $entry->amount,
                    'reference_no' => $entry->reference,
                    'account_number' => $entry->wallet?->account_number,
                    'balance_after' => (float) $entry->balance_after,
                ]);
            }

            $result = $this->ledger->approveLoadRequest(
                $request->user(),
                $id,
                ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'    => 'Load request approved and wallet credited.',
            'replayed'   => $result['replayed'] ?? false,
            'amount'     => $result['amount'] ?? null,
            'reference_no' => $result['reference_no'] ?? null,
        ]);
    }

    /** AJAX: reject a pending load request */
    public function rejectRequest(Request $request, int $id): JsonResponse
    {
        $this->assertCanLoad($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $queued = WalletLoadRequest::query()->findOrFail($id);

        try {
            if (filled($queued->account_number) || $queued->source === WalletLoadRequest::SOURCE_SUPPORT) {
                $this->astWallets->rejectLoadRequest(
                    $request->user(),
                    $id,
                    $validated['reason'],
                    ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
                );
            } else {
                $this->ledger->rejectLoadRequest(
                    $request->user(),
                    $id,
                    $validated['reason'],
                    ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
                );
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Load request rejected.']);
    }

    // -----------------------------------------------------------------------
    // 4. Customer Wallet Detail
    // -----------------------------------------------------------------------

    public function customerWallet(Request $request, int $userId): View
    {
        $this->assertStaff($request);

        /** @var User $customer */
        $customer = User::query()->findOrFail($userId);

        $wallets = AstWallet::query()
            ->where('user_id', $userId)
            ->orderBy('account_number')
            ->get();
        $wallet = $wallets->first();
        $walletIds = $wallets->pluck('id');
        $astBalance = (float) $wallets->sum('balance');

        $entries = $walletIds->isNotEmpty()
            ? AstLedgerEntry::query()
                ->with(['creator:id,name,role', 'wallet:id,account_number'])
                ->whereIn('wallet_id', $walletIds)
                ->orderByDesc('id')
                ->limit(500)
                ->get()
            : collect();

        $auditLogs = $walletIds->isNotEmpty()
            ? AstAuditLog::query()
                ->with(['actor:id,name,role', 'wallet:id,account_number'])
                ->whereIn('wallet_id', $walletIds)
                ->orderByDesc('id')
                ->limit(100)
                ->get()
            : collect();

        $ledgerStats = $walletIds->isNotEmpty()
            ? AstLedgerEntry::query()
                ->whereIn('wallet_id', $walletIds)
                ->selectRaw('type, count(*) as total, coalesce(sum(amount), 0) as amount')
                ->groupBy('type')
                ->get()
                ->keyBy('type')
            : collect();

        $canLoad = $request->user()->canLoadWallet();

        return view('pages.staff.ast.customer-wallet', compact(
            'customer',
            'wallet',
            'wallets',
            'astBalance',
            'entries',
            'auditLogs',
            'ledgerStats',
            'canLoad',
        ));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function assertStaff(Request $request): void
    {
        if (! $this->astWallets->isStaff($request->user())) {
            abort(403, 'Only staff can view AST wallet screens.');
        }
    }

    private function assertCanLoad(Request $request): void
    {
        if (! $request->user()?->canLoadWallet()) {
            abort(403, 'Your role does not have permission to load AST.');
        }
    }
}
