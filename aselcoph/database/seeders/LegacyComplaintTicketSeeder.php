<?php

namespace Database\Seeders;

use App\Services\LegacyComplaintImportService;
use Illuminate\Database\Seeder;

class LegacyComplaintTicketSeeder extends Seeder
{
    public function run(LegacyComplaintImportService $importer): void
    {
        $stats = $importer->import();

        $this->command?->info(
            "Legacy complaints → tickets: imported {$stats['imported']}, skipped {$stats['skipped']}, failed {$stats['failed']}."
        );
    }
}
