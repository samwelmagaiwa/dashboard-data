<?php

use Carbon\Carbon;
use App\Services\SyncService;

$syncService = app(SyncService::class);
$startDate = Carbon::create(2025, 6, 3);
$endDate = Carbon::create(2025, 6, 28);

echo "Starting sync from " . $startDate->toDateString() . " to " . $endDate->toDateString() . "...\n";

for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
    $dateString = $date->toDateString();
    echo "Syncing $dateString... ";
    try {
        $result = $syncService->syncForDate($dateString);
        if ($result['success']) {
            echo "DONE (Synced {$result['count']} records)\n";
        } else {
            echo "FAILED ({$result['error']})\n";
        }
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "Sync completed.\n";
