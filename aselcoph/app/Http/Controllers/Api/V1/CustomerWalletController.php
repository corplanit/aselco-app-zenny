<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientAstBalanceException;
use App\Events\WalletPaymentFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PayBillRequest;
use App\Models\AstLedgerEntry;
use App\Services\AstWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerWalletController extends Controller
{
    public function __construct(private AstWalletService $wallets)
    {
    }

    public function balance(Request $request): JsonResponse
    {
        return response()->json($this->wallets->summaryForUser($request->user()->id));
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $page */
        $page = $this->wallets->paginatedTransactionsForUser($request->user()->id, (int) ($validated['per_page'] ?? 15));

        $page->getCollection()->transform(fn (AstLedgerEntry $entry) => [
            ...$this->wallets->entryPayload($entry),
            'bill' => $entry->billingUpload ? [
                'id' => $entry->billingUpload->id,
                'amount' => (float) $entry->billingUpload->amount,
                'balance_due' => (float) $entry->billingUpload->balance_due,
                'status' => $entry->billingUpload->status,
            ] : null,
        ]);

        return response()->json($page);
    }

    public function payBill(PayBillRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        }

        try {
            $entry = $this->wallets->payBill(
                $request->user(),
                (int) $validated['billing_id'],
                (string) $validated['amount'],
                $idempotencyKey,
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta' => ['source' => 'mobile_bill_payment'],
                ]
            );
        } catch (InsufficientAstBalanceException $e) {
            WalletPaymentFailed::dispatch($request->user(), 'insufficient_balance', [
                'billing_id' => (string) $validated['billing_id'],
                'requested_amount' => (string) $validated['amount'],
                'balance' => $e->balance,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'AST_INSUFFICIENT_FUNDS',
                'balance' => (float) $e->balance,
            ], 422);
        } catch (InvalidArgumentException $e) {
            $status = 422;
            $code = 'AST_PAYMENT_INVALID';

            if ($e->getMessage() === 'Bill not found for this customer.') {
                $status = 404;
                $code = 'AST_BILL_NOT_FOUND';
            } elseif ($e->getMessage() === 'This bill has already been fully paid.') {
                $code = 'AST_BILL_ALREADY_PAID';
            } elseif ($e->getMessage() === 'Payment amount exceeds the remaining balance due.') {
                $code = 'AST_AMOUNT_MISMATCH';
            } elseif ($e->getMessage() === 'A valid idempotency key is required.') {
                $code = 'AST_IDEMPOTENCY_REQUIRED';
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $code,
            ], $status);
        }

        $replayed = $entry->wasRecentlyCreated === false && $entry->created_at?->lt(now()->subSeconds(1));

        return response()->json([
            'message' => $replayed ? 'Payment already recorded.' : 'Bill payment recorded.',
            'idempotent' => ! $entry->wasRecentlyCreated,
            'reference' => $entry->reference,
            'receipt_no' => $entry->reference,
            ...$this->wallets->entryPayload($entry),
        ], $entry->wasRecentlyCreated ? 201 : 200);
    }
}
