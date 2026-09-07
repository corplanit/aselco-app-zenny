<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'can.load-wallet' => \App\Http\Middleware\EnsureCanLoadWallet::class,
            'can.manage-tickets' => \App\Http\Middleware\EnsureCanManageTickets::class,
            'can.permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            return $user instanceof \App\Models\User
                ? $user->loginHomePath()
                : '/u/dashboard';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            return \App\Http\ApiErrorResponse::fromException($e, $request);
        });
    })->create();
