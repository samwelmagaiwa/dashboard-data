<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

set_time_limit(0);

$action = $argv[1] ?? 'status';

if ($action === 'status') {
    // Check current data
    $stats = DB::selectOne("SELECT MIN(visit_date) as min_date, MAX(visit_date) as max_date, COUNT(*) as total FROM visits");
    echo "Current data:\n";
    echo "  Date range: {$stats->min_date} to {$stats->max_date}\n";
    echo "  Total visits: {$stats->total}\n\n";

    // Sample multiple visits
    $dupes = DB::select("
        SELECT mr_number, visit_date, clinic_code, COUNT(*) as visit_count 
        FROM visits 
        GROUP BY mr_number, visit_date, clinic_code 
        HAVING COUNT(*) > 1 
        LIMIT 5
    ");
    echo "Sample patients with multiple visits at same clinic on same day:\n";
    foreach ($dupes as $d) {
        echo "  MR: {$d->mr_number}, Date: {$d->visit_date}, Clinic: {$d->clinic_code}, Visits: {$d->visit_count}\n";
    }
} 
elseif ($action === 'reaggregate') {
    $start = $argv[2] ?? '2025-01-01';
    $end = $argv[3] ?? date('Y-m-d');
    
    echo "Re-aggregating stats from {$start} to {$end}...\n";
    $syncService = app(\App\Services\SyncService::class);
    
    $startDate = \Carbon\Carbon::parse($start);
    $endDate = \Carbon\Carbon::parse($end);
    $count = 0;
    
    for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
        $syncService->updateAggregatedStats($date->toDateString());
        $count++;
        if ($count % 30 === 0) {
            echo "  Processed {$count} days...\n";
        }
    }
    echo "Done! Re-aggregated {$count} days.\n";
}
elseif ($action === 'sync') {
    $date = $argv[2] ?? date('Y-m-d');
    echo "Syncing {$date}...\n";
    $syncService = app(\App\Services\SyncService::class);
    $result = $syncService->syncForDate($date);
    print_r($result);
}
