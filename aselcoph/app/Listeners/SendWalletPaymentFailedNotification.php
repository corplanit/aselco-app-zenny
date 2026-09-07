<?php

namespace App\Listeners;

use App\Events\WalletPaymentFailed;
use App\Mail\SystemNotificationMail;
use App\Services\NotificationDispatchService;
use App\Services\SmsDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWalletPaymentFailedNotification implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function __construct(
        private NotificationDispatchService $notifications,
        private SmsDeliveryService $sms,
    ) {
    }

    public function handle(WalletPaymentFailed $event): void
    {
        $customer = $event->customer;
        $title = __('notifications.wallet.payment_failed_title');
        $body = __('notifications.wallet.payment_failed_body', ['reason' => $event->reason]);

        $result = $this->notifications->deliverUserNotification($customer, 'billing', $title, $body, $event->details);

        if (filled($customer->email)) {
            Mail::to($customer->email)->send(new SystemNotificationMail($title, $title, $body));
        }

        if (($result['pushed_count'] === 0 || ! $result['had_device_tokens']) && filled($customer->contact_no)) {
            $this->sms->send((string) $customer->contact_no, $body);
        }
    }
}
