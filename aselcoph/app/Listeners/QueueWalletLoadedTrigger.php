<?php

namespace App\Listeners;

use App\Events\WalletLoaded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class QueueWalletLoadedTrigger implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function handle(WalletLoaded $event): void
    {
        Log::info('wallet.trigger', [
            'name' => 'wallet.loaded',
            'customer_id' => $event->customer->id,
            'transaction_id' => $event->transaction->id,
        ]);
    }
}
