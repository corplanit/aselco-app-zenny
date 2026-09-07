<?php

namespace App\Listeners;

use App\Events\TicketLifecycleEvent;
use App\Mail\SystemNotificationMail;
use App\Models\User;
use App\Services\NotificationDispatchService;
use App\Services\SmsDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendTicketLifecycleNotification implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function __construct(
        private NotificationDispatchService $notifications,
        private SmsDeliveryService $sms,
    ) {
    }

    public function handle(TicketLifecycleEvent $event): void
    {
        $ticket = $event->ticket->loadMissing('customer', 'assignee');
        $ticketNo = $ticket->ticket_no;
        $department = $ticket->assigned_department ?? __('notifications.ticket.staff_assigned_title');
        $deepLink = '/tickets/'.$ticket->id;

        match ($event->name) {
            'ticket.created' => $this->notifyCustomer($ticket->customer, 'service',
                __('notifications.ticket.submitted_title'),
                __('notifications.ticket.submitted_body', ['ticket_no' => $ticketNo]),
                ['ticket_id' => (string) $ticket->id, 'ticket_no' => $ticketNo, 'deep_link' => $deepLink]
            ),
            'ticket.assigned' => $this->notifyAssignment($event, $department),
            'ticket.feedback_requested' => $this->notifyCustomer($ticket->customer, 'service',
                __('notifications.ticket.feedback_title'),
                __('notifications.ticket.feedback_body', ['ticket_no' => $ticketNo]),
                ['ticket_id' => (string) $ticket->id, 'ticket_no' => $ticketNo, 'deep_link' => $deepLink]
            ),
            'ticket.resolved' => $this->notifyCustomer($ticket->customer, 'service',
                __('notifications.ticket.resolved_title'),
                __('notifications.ticket.resolved_body', ['ticket_no' => $ticketNo]),
                ['ticket_id' => (string) $ticket->id, 'ticket_no' => $ticketNo, 'deep_link' => $deepLink]
            ),
            'ticket.reopened' => $this->notifyCustomer($ticket->customer, 'service',
                __('notifications.ticket.reopened_title'),
                __('notifications.ticket.reopened_body', ['ticket_no' => $ticketNo]),
                ['ticket_id' => (string) $ticket->id, 'ticket_no' => $ticketNo, 'deep_link' => $deepLink]
            ),
            'ticket.closed' => $this->notifyCustomer($ticket->customer, 'service',
                __('notifications.ticket.closed_title'),
                __('notifications.ticket.closed_body', ['ticket_no' => $ticketNo]),
                ['ticket_id' => (string) $ticket->id, 'ticket_no' => $ticketNo, 'deep_link' => $deepLink]
            ),
            'ticket.escalated' => $this->notifyEscalation($event),
            default => null,
        };
    }

    private function notifyAssignment(TicketLifecycleEvent $event, string $department): void
    {
        $ticket = $event->ticket;
        $ticketNo = $ticket->ticket_no;
        $payload = [
            'ticket_id' => (string) $ticket->id,
            'ticket_no' => $ticketNo,
            'deep_link' => '/tickets/'.$ticket->id,
        ];

        $this->notifyCustomer(
            $ticket->customer,
            'service',
            __('notifications.ticket.assigned_title'),
            __('notifications.ticket.assigned_body', ['ticket_no' => $ticketNo, 'department' => $department]),
            $payload
        );

        $recipients = $ticket->assigned_to
            ? User::query()->whereKey($ticket->assigned_to)->get()
            : User::query()->where('department_code', $ticket->assigned_department)->get();

        foreach ($recipients as $recipient) {
            $title = __('notifications.ticket.staff_assigned_title');
            $body = __('notifications.ticket.staff_assigned_body', [
                'ticket_no' => $ticketNo,
                'department' => $department,
            ]);

            $this->notifications->deliverUserNotification($recipient, 'alert', $title, $body, $payload);

            if (filled($recipient->email)) {
                Mail::to($recipient->email)->send(new SystemNotificationMail($title, $title, $body));
            }
        }
    }

    private function notifyEscalation(TicketLifecycleEvent $event): void
    {
        $ticket = $event->ticket;
        $department = (string) ($event->context['escalated_to'] ?? $ticket->assigned_department);
        $recipients = User::query()
            ->where('department_code', $department)
            ->whereIn('role', config('tickets.supervisor_roles', []))
            ->get();

        foreach ($recipients as $recipient) {
            $title = __('notifications.ticket.staff_escalated_title');
            $body = __('notifications.ticket.staff_escalated_body', ['ticket_no' => $ticket->ticket_no]);
            $payload = [
                'ticket_id' => (string) $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'deep_link' => '/tickets/'.$ticket->id,
            ];

            $this->notifications->deliverUserNotification($recipient, 'alert', $title, $body, $payload);

            if (filled($recipient->email)) {
                Mail::to($recipient->email)->send(new SystemNotificationMail($title, $title, $body));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyCustomer(?User $customer, string $category, string $title, string $body, array $payload): void
    {
        if (! $customer) {
            return;
        }

        $result = $this->notifications->deliverUserNotification($customer, $category, $title, $body, $payload);

        if (($result['pushed_count'] === 0 || ! $result['had_device_tokens']) && filled($customer->contact_no)) {
            $this->sms->send((string) $customer->contact_no, $body);
        }
    }
}
