<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AstAccountForbiddenException;
use App\Exceptions\InsufficientAstBalanceException;
use App\Http\Controllers\Controller;
use App\Services\AstWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WalletController extends Controller
{
    public function __construct(private AstWalletService $wallets)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $primary = trim((string) $request->query('account_number', ''));

        return response()->json(
            $this->wallets->summaryForUser(
                $request->user()->id,
                $primary !== '' ? $primary : null
            )
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        try {
            $account = trim((string) $request->query('account_number', ''));
            $entries = $this->wallets->transactionsForUser(
                $request->user()->id,
                $account !== '' ? $account : null,
                (int) $request->query('limit', 50)
            );
        } catch (AstAccountForbiddenException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'AST_ACCOUNT_FORBIDDEN',
            ], 403);
        }

        return response()->json(['data' => $entries]);
    }

    public function pay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        }

        try {
            if ($idempotencyKey === '') {
                throw new InvalidArgumentException('A valid idempotency key is required.');
            }

            $amount = $this->wallets->normalizeAmount((string) $validated['amount']);
            $entry = $this->wallets->pay(
                $request->user(),
                (string) $validated['account_number'],
                $amount,
                $idempotencyKey,
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta' => [
                        'source' => 'mobile',
                    ],
                ]
            );
        } catch (InsufficientAstBalanceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'AST_INSUFFICIENT_FUNDS',
                'balance' => (float) $e->balance,
            ], 422);
        } catch (AstAccountForbiddenException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'AST_ACCOUNT_FORBIDDEN',
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (HttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        $replayed = $entry->wasRecentlyCreated === false
            && $entry->created_at?->lt(now()->subSeconds(1));

        return response()->json([
            'message' => $replayed ? 'Payment already recorded.' : 'Paid with AST.',
            'idempotent' => ! $entry->wasRecentlyCreated,
            ...$this->wallets->entryPayload($entry),
            'balance' => (float) $entry->balance_after,
        ], $entry->wasRecentlyCreated ? 201 : 200);
    }
}
