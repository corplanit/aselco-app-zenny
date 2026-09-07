<?php

namespace App\Listeners;

use App\Events\WalletLoadFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class QueueWalletLoadFailedTrigger implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function handle(WalletLoadFailed $event): void
    {
        Log::warning('wallet.trigger', [
            'name' => 'wallet.load_failed',
            'actor_id' => $event->actor?->id,
            'reason' => $event->reason,
            'details' => $event->details,
        ]);
    }
}
