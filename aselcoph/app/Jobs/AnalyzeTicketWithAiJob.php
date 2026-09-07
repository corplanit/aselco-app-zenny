<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\Ai\TicketAiAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeTicketWithAiJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $ticketId)
    {
        $this->onQueue((string) config('tickets.ai.queue', 'default'));
    }

    public function handle(TicketAiAnalyzer $analyzer): void
    {
        if (! (bool) config('tickets.ai.enabled', true)) {
            return;
        }

        $ticket = Ticket::query()->find($this->ticketId);
        if ($ticket === null) {
            return;
        }

        try {
            $analyzer->analyze($ticket);
        } catch (Throwable $e) {
            Log::error('ticket.ai.job_failed', [
                'ticket_id' => $this->ticketId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
