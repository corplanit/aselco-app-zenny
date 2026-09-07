<?php

namespace App\Providers;

use App\Models\AccountLink;
use App\Models\BillingUpload;
use App\Models\User;
use App\Observers\AccountLinkObserver;
use App\Observers\BillingUploadObserver;
use App\Services\Access\AccessService;
use App\Services\Ai\Contracts\AiCompletionClient;
use App\Services\Ai\OpenAiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use App\Services\Access\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiCompletionClient::class, OpenAiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        BillingUpload::observe(BillingUploadObserver::class);
        AccountLink::observe(AccountLinkObserver::class);

        foreach (config('access.permissions', []) as $row) {
            Gate::define($row['code'], fn (User $user) => app(AccessService::class)->allows($user, $row['code']));
        }

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }
            $event->user->forceFill([
                'last_login_at' => now(),
                'failed_login_count' => 0,
                'availability_status' => $event->user->availability_status === 'offline' ? 'available' : $event->user->availability_status,
            ])->saveQuietly();
            app(ActivityLogger::class)->record('login', $event->user);
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(ActivityLogger::class)->record('logout', $event->user);
            }
        });
        Event::listen(Failed::class, function (Failed $event): void {
            $user = $event->user instanceof User
                ? $event->user
                : User::query()->where('email', $event->credentials['email'] ?? '')->first();
            if (! $user) {
                return;
            }
            $attempts = (int) $user->failed_login_count + 1;
            $limit = (int) app(AccessService::class)->setting('lockout_attempts', 5);
            $minutes = (int) app(AccessService::class)->setting('lockout_minutes', 15);
            $payload = ['failed_login_count' => $attempts];
            if ($attempts >= $limit) {
                $payload['locked_until'] = now()->addMinutes($minutes);
                $payload['account_status'] = User::STATUS_LOCKED;
            }
            $user->forceFill($payload)->saveQuietly();
            app(ActivityLogger::class)->record('login.failed', $user);
        });

        RateLimiter::for('api-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });
        RateLimiter::for('api-register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });
        RateLimiter::for('api-membership', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('api-ledger', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('api-wallet', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('admin-wallet-load', function (Request $request) {
            return Limit::perMinute(20)->by('admin-wallet-load|'.($request->user()?->id ?: $request->ip()));
        });
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('api-tickets', function (Request $request) {
            return Limit::perMinute(40)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('api-notifications', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('api-ticket-create', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('api-ai', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        Queue::failing(function (JobFailed $event): void {
            Log::error('queue.job_failed', [
                'uuid' => $event->job->uuid(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'name' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }
}
