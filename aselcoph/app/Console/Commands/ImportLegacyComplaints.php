<?php

namespace App\Console\Commands;

use App\Services\LegacyComplaintImportService;
use Illuminate\Console\Command;

class ImportLegacyComplaints extends Command
{
    protected $signature = 'tickets:import-legacy-complaints
                            {--dry-run : Count rows without writing tickets}
                            {--fresh : Replace previously imported legacy tickets}';

    protected $description = 'Copy customer_complaints rows into the ticket queue (idempotent).';

    public function handle(LegacyComplaintImportService $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $stats = $importer->import($dryRun, $fresh);

        $verb = $dryRun ? 'Would import' : 'Imported';
        $this->info("{$verb} {$stats['imported']} complaint(s). Skipped {$stats['skipped']}. Failed {$stats['failed']}.");

        if ($stats['tickets'] !== []) {
            $this->table(
                ['Complaint', 'Ticket ID', 'Ticket no', 'Status'],
                array_map(fn (array $row) => [
                    $row['complaint_id'],
                    $row['ticket_id'] ?: '—',
                    $row['ticket_no'],
                    $row['status'],
                ], $stats['tickets'])
            );
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
