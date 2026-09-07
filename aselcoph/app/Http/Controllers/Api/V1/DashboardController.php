<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AccountLinkResource;
use App\Http\Resources\Api\V1\LinkedAccountResource;
use App\Models\AccountLink;
use App\Models\BillingUpload;
use App\Models\TAccountRaw;
use App\Services\AstWalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends Controller
{
    /**
     * Home dashboard payload: consumer identity, service account, billing totals, and AST wallet.
     */
    public function summary(Request $request, AstWalletService $wallets): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $links = AccountLink::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $linkedAccounts = collect();
        try {
            $linkedAccounts = TAccountRaw::query()
                ->where('user_id', $userId)
                ->get();
        } catch (Throwable) {
            // Raw ledger table may be unavailable.
        }

        $primaryLinked = $linkedAccounts->first();
        $primaryLink = $links->first(fn (AccountLink $link) => $link->validated_at !== null)
            ?? $links->first();

        $service = null;
        if ($primaryLinked) {
            $service = [
                'account_number' => (string) $primaryLinked->account_no,
                'owner_name' => $primaryLinked->customer,
                'status' => $primaryLinked->status
                    ? strtolower((string) $primaryLinked->status)
                    : 'linked',
                'meter_no' => $primaryLinked->meter_no ?? null,
                'address' => $primaryLinked->address ?? null,
                'rate_class' => $primaryLinked->rate_class ?? null,
                'source' => 'linked_account',
            ];
        } elseif ($primaryLink) {
            $service = [
                'account_number' => (string) $primaryLink->account_number,
                'owner_name' => $primaryLink->owner_name,
                'status' => $primaryLink->validated_at ? 'validated' : 'pending',
                'meter_no' => null,
                'address' => null,
                'rate_class' => null,
                'source' => 'account_link',
            ];
        }

        $billing = [
            'current_bill_id' => null,
            'amount_due' => null,
            'pending_count' => 0,
            'billing_period' => null,
            'due_date' => null,
            'as_of' => now()->toIso8601String(),
            'has_data' => false,
            'outstanding_bills' => [],
        ];

        try {
            $pending = BillingUpload::query()
                ->with('accountLink')
                ->whereHas('accountLink', fn ($query) => $query->where('user_id', $userId))
                ->whereIn('status', [
                    BillingUpload::STATUS_PENDING,
                    BillingUpload::STATUS_PARTIALLY_PAID,
                    'Pending',
                ])
                ->orderByDesc('billing_date')
                ->get();

            if ($pending->isNotEmpty()) {
                $latest = $pending->first();
                $date = $latest->billing_date
                    ? Carbon::parse($latest->billing_date)
                    : now();

                $billing = [
                    'current_bill_id' => $latest->id,
                    'amount_due' => (float) $pending->sum(fn (BillingUpload $item) => (float) ($item->balance_due ?? $item->amount)),
                    'pending_count' => $pending->count(),
                    'billing_period' => $date->format('M Y'),
                    'due_date' => $date->copy()->endOfMonth()->format('M d, Y'),
                    'as_of' => now()->toIso8601String(),
                    'has_data' => true,
                    'outstanding_bills' => $pending->map(fn (BillingUpload $item) => [
                        'id' => $item->id,
                        'amount' => (float) $item->amount,
                        'balance_due' => (float) ($item->balance_due ?? $item->amount),
                        'status' => $item->status,
                        'account_number' => $item->accountLink
                            ? (string) $item->accountLink->account_number
                            : null,
                        'billing_date' => $item->billing_date
                            ? Carbon::parse($item->billing_date)->toDateString()
                            : null,
                    ])->values()->all(),
                ];
            }
        } catch (Throwable) {
            // Billing uploads may be unavailable; mobile falls back to mock amounts.
        }

        return response()->json([
            'consumer' => [
                'name' => $user->name,
                'email' => $user->email,
                'contact_no' => $user->contact_no,
            ],
            'service' => $service,
            'billing' => $billing,
            'linked_accounts' => LinkedAccountResource::collection($linkedAccounts)->resolve(),
            'account_links' => AccountLinkResource::collection($links)->resolve(),
            // Do not force the service/primary meter as the wallet account — AST may sit on
            // another linked account. summaryForUser picks the funded account automatically.
            'wallet' => $wallets->summaryForUser($userId),
        ]);
    }
}
