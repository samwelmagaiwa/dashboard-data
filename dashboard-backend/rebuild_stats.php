<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$start = \Carbon\Carbon::parse('2024-01-01');
$end = \Carbon\Carbon::today();
$service = app(\App\Services\SyncService::class);
$count = 0;

echo "Starting re-aggregation from " . $start->toDateString() . " to " . $end->toDateString() . "...\n";

for ($date = $start; $date->lte($end); $date->addDay()) {
    try {
        $service->updateAggregatedStats($date->toDateString());
        $count++;
        if ($count % 30 == 0) echo "Processed $count days (" . $date->toDateString() . ")...\n";
    } catch (\Exception $e) {
        echo "Error on " . $date->toDateString() . ": " . $e->getMessage() . "\n";
    }
}

echo "Done! Re-aggregated $count days.\n";
