<?php

namespace App\Listeners;

use App\Events\TicketLifecycleEvent;
use App\Jobs\AnalyzeTicketWithAiJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AnalyzeTicketWithAi implements ShouldQueue
{
    use RetriesQueuedNotifications;

    public function handle(TicketLifecycleEvent $event): void
    {
        if ($event->name !== 'ticket.created') {
            return;
        }

        if (! (bool) config('tickets.ai.enabled', true)
            || ! (bool) config('tickets.ai.auto_analyze_on_create', true)) {
            return;
        }

        try {
            AnalyzeTicketWithAiJob::dispatch($event->ticket->id);
        } catch (\Throwable $e) {
            Log::warning('ticket.ai.dispatch_failed', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
