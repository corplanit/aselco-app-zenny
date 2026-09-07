<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoadWalletRequest;
use App\Models\User;
use App\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminWalletController extends Controller
{
    public function __construct(private WalletLedgerService $ledger)
    {
    }

    public function load(LoadWalletRequest $request): JsonResponse
    {
        try {
            $result = $this->ledger->loadByAdmin(
                $request->user(),
                (int) $request->validated('customer_id'),
                (string) $request->validated('amount'),
                (string) $request->validated('reference_no'),
                $request->validated('remarks'),
                (string) $request->input('idempotency_key'),
                $this->context($request)
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'WALLET_LOAD_INVALID',
            ], 422);
        }

        $status = $result['replayed'] ?? false ? 200 : ($result['status'] === 'pending_approval' ? 202 : 201);

        return response()->json($this->publicPayload($result), $status);
    }

    public function transactions(Request $request, int $customerId): JsonResponse
    {
        $customer = User::query()->find($customerId);
        if ($customer === null || ! $customer->isActiveCustomer()) {
            return response()->json([
                'message' => 'customer_id must be an active customer account.',
                'code' => 'WALLET_CUSTOMER_INVALID',
            ], 422);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'in:load,payment,reversal,adjustment'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->ledger->paginateTransactions($customerId, $validated);

        return response()->json($page);
    }

    public function loadRequests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,completed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->ledger->paginateLoadRequests(
                $validated['status'] ?? null,
                (int) ($validated['per_page'] ?? 15)
            )
        );
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->ledger->approveLoadRequest($request->user(), $id, $this->context($request));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'WALLET_LOAD_INVALID',
            ], 422);
        }

        $status = $result['replayed'] ?? false ? 200 : 201;

        return response()->json($this->publicPayload($result), $status);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->ledger->rejectLoadRequest(
                $request->user(),
                $id,
                $validated['remarks'] ?? null,
                $this->context($request)
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'WALLET_LOAD_INVALID',
            ], 422);
        }

        return response()->json($this->publicPayload($result));
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->ledger->adminSummary());
    }

    /**
     * @return array{ip: string|null, user_agent: string|null}
     */
    private function context(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function publicPayload(array $result): array
    {
        return [
            'reference_no' => $result['reference_no'],
            'amount' => $result['amount'],
            'new_balance' => $result['new_balance'],
            'status' => $result['status'],
            'transaction_id' => $result['transaction_id'],
        ];
    }
}
