<?php

namespace App\Listeners;

use App\Events\WalletLoaded;
use App\Mail\WalletLoadedMail;
use App\Services\NotificationDispatchService;
use App\Services\SmsDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWalletLoadedNotification implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function __construct(
        private NotificationDispatchService $notifications,
        private SmsDeliveryService $sms,
    ) {}

    public function handle(WalletLoaded $event): void
    {
        $customer = $event->customer;
        $txn = $event->transaction;
        $amount = number_format((float) $txn->amount, 2);
        $balance = number_format((float) $txn->balance_after, 2);
        $title = __('notifications.wallet.loaded_title');
        $body = __('notifications.wallet.loaded_body', ['amount' => $amount, 'balance' => $balance]);

        $result = $this->notifications->deliverUserNotification($customer, 'billing', $title, $body, [
            'deep_link' => '/tabs/pay',
            'reference_no' => $txn->reference_no,
            'transaction_id' => (string) $txn->id,
            'amount' => $amount,
            'new_balance' => $balance,
        ]);

        if (filled($customer->email)) {
            Mail::to($customer->email)->send(new WalletLoadedMail($customer, $txn));
        }

        if (($result['pushed_count'] === 0 || ! $result['had_device_tokens']) && filled($customer->contact_no)) {
            $this->sms->send((string) $customer->contact_no, $body);
        }
    }

    public function failed(WalletLoaded $event, Throwable $exception): void
    {
        Log::warning('wallet.loaded_notification_failed', [
            'customer_id' => $event->customer->id,
            'transaction_id' => $event->transaction->id,
            'message' => $exception->getMessage(),
        ]);
    }
}
