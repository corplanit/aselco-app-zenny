<?php

namespace App\Http\Middleware;

use App\Models\WalletAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanLoadWallet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! app(\App\Services\Access\AccessService::class)->allows($user, 'wallet.load')) {
            WalletAuditLog::query()->create([
                'wallet_id' => null,
                'actor_id' => $user?->id,
                'actor_type' => WalletAuditLog::ACTOR_ADMIN,
                'action' => 'load_unauthorized',
                'old_value' => null,
                'new_value' => [
                    'path' => $request->path(),
                    'role' => $user?->role,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'You are not allowed to load AST wallets.');
        }

        return $next($request);
    }
}
