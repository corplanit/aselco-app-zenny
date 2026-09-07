<?php

namespace App\Listeners;

use App\Events\WalletPaymentMade;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class QueueWalletPaymentMadeTrigger implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function handle(WalletPaymentMade $event): void
    {
        Log::info('wallet.trigger', [
            'name' => 'wallet.payment_made',
            'customer_id' => $event->customer->id,
            'transaction_id' => $event->entry->id,
            'context' => $event->context,
        ]);
    }
}
