<?php

namespace App\Http\Controllers;

use App\Models\AstLedgerEntry;
use App\Services\AstWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AstWalletAdminController extends Controller
{
    public function __construct(private AstWalletService $wallets)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $this->assertStaff($request);

        $accountNumber = $this->wallets->normalizeAccount((string) $request->query('account_number', ''));
        $wallet = \App\Models\AstWallet::query()
            ->where('account_number', $accountNumber)
            ->first();

        $entries = [];
        if ($wallet) {
            $entries = $wallet->entries()
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (AstLedgerEntry $entry) => $this->wallets->entryPayload($entry))
                ->all();
        }

        return response()->json([
            'account_number' => $accountNumber,
            'balance' => $wallet ? (float) $wallet->balance : 0.0,
            'unit' => AstWalletService::UNIT,
            'user_id' => $wallet?->user_id,
            'entries' => $entries,
        ]);
    }

    public function load(Request $request): JsonResponse
    {
        $this->assertStaff($request);

        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        }
        if ($idempotencyKey === '') {
            $idempotencyKey = 'admin-load-'.(string) $request->user()->id.'-'.(string) \Illuminate\Support\Str::uuid();
        }

        try {
            $amount = $this->wallets->normalizeAmount((string) $validated['amount']);
            $entry = $this->wallets->load(
                $request->user(),
                (string) $validated['account_number'],
                $amount,
                $idempotencyKey,
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta' => ['source' => 'admin'],
                ]
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $entry->wasRecentlyCreated ? 'AST loaded successfully.' : 'Load already recorded.',
            'idempotent' => ! $entry->wasRecentlyCreated,
            ...$this->wallets->entryPayload($entry),
            'balance' => (float) $entry->balance_after,
        ], $entry->wasRecentlyCreated ? 201 : 200);
    }

    public function cisQueue(Request $request): View
    {
        $this->assertStaff($request);

        $list = \App\Support\ListQuery::from(
            $request,
            filterKeys: [],
            sortable: ['id' => 'id', 'created_at' => 'created_at', 'amount' => 'amount'],
            defaultSort: 'id',
            defaultDir: 'asc',
            defaultPerPage: 25,
        );

        $query = AstLedgerEntry::query()
            ->with(['wallet', 'creator:id,name,email'])
            ->where('type', AstLedgerEntry::TYPE_PAY)
            ->where('cis_status', AstLedgerEntry::CIS_PENDING_POST);

        if ($list['search']) {
            $term = $list['search'];
            $query->where(function ($q) use ($term) {
                $q->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('wallet', fn ($w) => $w->where('account_number', 'like', "%{$term}%"))
                    ->orWhereHas('creator', fn ($c) => $c->where('name', 'like', "%{$term}%"));
            });
        }

        $sortCol = match ($list['sort']) {
            'created_at' => 'created_at',
            'amount' => 'amount',
            default => 'id',
        };
        $payments = $query->orderBy($sortCol, $list['dir'])->paginate($list['per_page'])->withQueryString();

        return view('pages.staff.ast-cis-queue', [
            'payments' => $payments,
            'list' => $list,
            'filters' => ['search' => $list['search'], 'sort' => $list['sort'], 'dir' => $list['dir']],
            'activeFilterCount' => $list['active_filter_count'],
        ]);
    }

    public function postCis(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->assertStaff($request);

        $validated = $request->validate([
            'cis_external_ref' => ['required', 'string', 'max:120'],
        ]);

        try {
            $entry = $this->wallets->postToCis(
                $request->user(),
                $id,
                (string) $validated['cis_external_ref'],
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Payment marked as posted to CIS.',
                ...$this->wallets->entryPayload($entry),
            ]);
        }

        return back()->with('success', 'Payment '.$entry->reference.' marked as posted to CIS.');
    }

    private function assertStaff(Request $request): void
    {
        if (! $this->wallets->isStaff($request->user())) {
            abort(403, 'Only staff can manage AST wallets.');
        }
    }
}
