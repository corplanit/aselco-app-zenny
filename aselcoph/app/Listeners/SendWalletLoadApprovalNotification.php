<?php

namespace App\Listeners;

use App\Events\WalletLoadApprovalRequested;
use App\Mail\SystemNotificationMail;
use App\Models\User;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWalletLoadApprovalNotification implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function __construct(private NotificationDispatchService $notifications)
    {
    }

    public function handle(WalletLoadApprovalRequested $event): void
    {
        $request = $event->request;
        $maker = $event->maker;
        $title = __('notifications.wallet.load_approval_title');
        $body = __('notifications.wallet.load_approval_body', [
            'reference_no' => $request->reference_no,
            'amount' => number_format((float) $request->amount, 2),
        ]);

        $approvers = User::query()
            ->where('id', '<>', $maker->id)
            ->whereIn('role', config('ast.load_wallet_roles', []))
            ->get();

        foreach ($approvers as $approver) {
            $payload = [
                'load_request_id' => (string) $request->id,
                'reference_no' => $request->reference_no,
            ];

            $this->notifications->deliverUserNotification($approver, 'alert', $title, $body, $payload);

            if (filled($approver->email)) {
                Mail::to($approver->email)->send(new SystemNotificationMail($title, $title, $body));
            }
        }
    }
}
