<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class RefreshDuplicateOccurrences extends Command
{
    protected $signature = 'dashboard:refresh-duplicate-occurrences {date : Date in YYYY-MM-DD or YYYYMMDD format}';

    protected $description = 'Refresh duplicate occurrence counts for a date without purging existing dashboard data';

    public function handle(SyncService $syncService): int
    {
        $date = $this->argument('date');

        $this->info("Refreshing duplicate occurrences for {$date}...");

        $result = $syncService->syncForDate($date, false);

        if (!($result['success'] ?? false)) {
            $this->error($result['error'] ?? 'Failed to refresh duplicate occurrences.');
            return self::FAILURE;
        }

        $this->info('Duplicate occurrence counts refreshed successfully.');
        return self::SUCCESS;
    }
}
