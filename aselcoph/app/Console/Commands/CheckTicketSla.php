<?php

namespace App\Console\Commands;

use App\Services\TicketService;
use Illuminate\Console\Command;

class CheckTicketSla extends Command
{
    protected $signature = 'tickets:check-sla';

    protected $description = 'Escalate tickets that have breached their SLA.';

    public function handle(TicketService $tickets): int
    {
        $count = $tickets->checkSlaBreaches();

        $this->info("Processed {$count} SLA breach(es).");

        return self::SUCCESS;
    }
}
