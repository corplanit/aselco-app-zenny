<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageTickets
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! app(\App\Services\Access\AccessService::class)->allows($user, 'tickets.view')) {
            abort(403, 'You are not allowed to manage tickets.');
        }

        return $next($request);
    }
}
