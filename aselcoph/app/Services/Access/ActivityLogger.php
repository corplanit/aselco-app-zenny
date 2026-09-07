<?php

namespace App\Services\Access;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $action,
        ?User $actor = null,
        ?Model $target = null,
        ?array $old = null,
        ?array $new = null,
    ): void {
        if (! Schema::hasTable('user_activity_logs')) {
            return;
        }

        $actor ??= Auth::user();
        $request = request();

        UserActivityLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target?->getKey(),
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
