<?php

namespace App\Http\Middleware;

use App\Services\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private AccessService $access)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->access->allows($user, $permission)) {
            abort(403, 'You are not allowed to perform this action.');
        }

        return $next($request);
    }
}
