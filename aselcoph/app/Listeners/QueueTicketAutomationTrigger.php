<?php

namespace App\Listeners;

use App\Events\TicketLifecycleEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class QueueTicketAutomationTrigger implements ShouldQueue
{
    use RetriesQueuedNotifications;
    public function handle(TicketLifecycleEvent $event): void
    {
        Log::info('ticket.trigger', [
            'name' => $event->name,
            'ticket_id' => $event->ticket->id,
            'actor_id' => $event->actor?->id,
            'context' => $event->context,
        ]);
    }
}
