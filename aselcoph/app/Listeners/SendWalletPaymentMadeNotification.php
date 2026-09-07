<?php

namespace App\Listeners;

use App\Events\WalletPaymentMade;
use App\Mail\SystemNotificationMail;
use App\Services\NotificationDispatchService;
use App\Services\SmsDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWalletPaymentMadeNotification implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function __construct(
        private NotificationDispatchService $notifications,
        private SmsDeliveryService $sms,
    ) {
    }

    public function handle(WalletPaymentMade $event): void
    {
        $customer = $event->customer;
        $entry = $event->entry->loadMissing('wallet');
        $amount = number_format((float) $entry->amount, 2);
        $title = __('notifications.wallet.payment_title');
        $body = __('notifications.wallet.payment_body', [
            'amount' => $amount,
            'reference' => $entry->reference,
        ]);

        $result = $this->notifications->deliverUserNotification($customer, 'billing', $title, $body, [
            'deep_link' => '/tabs/pay',
            'reference' => $entry->reference,
            'ledger_entry_id' => (string) $entry->id,
            'billing_upload_id' => (string) ($entry->billing_upload_id ?? ''),
        ]);

        if (filled($customer->email)) {
            Mail::to($customer->email)->send(new SystemNotificationMail($title, $title, $body));
        }

        if (($result['pushed_count'] === 0 || ! $result['had_device_tokens']) && filled($customer->contact_no)) {
            $this->sms->send((string) $customer->contact_no, $body);
        }
    }
}
