<?php

namespace App\Listeners;

use App\Events\WalletLoadFailed;
use App\Mail\SystemNotificationMail;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWalletLoadFailedNotification implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function __construct(private NotificationDispatchService $notifications)
    {
    }

    public function handle(WalletLoadFailed $event): void
    {
        if (! $event->actor) {
            return;
        }

        $title = __('notifications.wallet.load_failed_title');
        $body = __('notifications.wallet.load_failed_body', ['reason' => $event->reason]);

        $this->notifications->deliverUserNotification($event->actor, 'alert', $title, $body, $event->details);

        if (filled($event->actor->email)) {
            Mail::to($event->actor->email)->send(new SystemNotificationMail($title, $title, $body));
        }
    }
}
