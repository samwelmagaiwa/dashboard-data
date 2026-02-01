<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$date = '2026-02-01';

echo "--- Verifying Optimization for $date ---\n";

// 1. Emergency Verification
$oldEmergency = \App\Models\Visit::whereDate('visit_date', $date)->where('dept_name', 'LIKE', '%EMERGENCY%')->count();
$newEmergency = \App\Models\Visit::whereDate('visit_date', $date)->where('dept_code', '150')->count();
echo "Emergency (Old LIKE): $oldEmergency\n";
echo "Emergency (New CODE): $newEmergency\n";
if ($oldEmergency === $newEmergency) echo "✅ Emergency Check Passed\n"; else echo "❌ Emergency Mismatch\n";

// 2. Public Verification
$oldPublic = \App\Models\Visit::whereDate('visit_date', $date)->where('pat_catg_nm', 'LIKE', '%PUBLIC%')->count();
$newPublic = \App\Models\Visit::whereDate('visit_date', $date)->where('pat_catg', '001')->count();
echo "Public (Old LIKE): $oldPublic\n";
echo "Public (New CODE): $newPublic\n";
if ($oldPublic === $newPublic) echo "✅ Public Check Passed\n"; else echo "❌ Public Mismatch\n";

// 3. Foreigner Verification
$oldForeigner = \App\Models\Visit::whereDate('visit_date', $date)->where('pat_catg_nm', 'LIKE', '%FOREIGNER%')->count();
$newForeigner = \App\Models\Visit::whereDate('visit_date', $date)->where('pat_catg', '016')->count();
echo "Foreigner (Old LIKE): $oldForeigner\n";
echo "Foreigner (New CODE): $newForeigner\n";
if ($oldForeigner === $newForeigner) echo "✅ Foreigner Check Passed\n"; else echo "❌ Foreigner Mismatch\n";

// 4. Run Actual Aggregation
echo "\nRunning SyncService::updateAggregatedStats...\n";
try {
    $service = app(\App\Services\SyncService::class);
    $service->updateAggregatedStats($date);
    $stat = \App\Models\DailyDashboardStat::where('stat_date', $date)->first();
    echo "Aggr Emergency: " . $stat->emergency . "\n";
    echo "Aggr Public: " . $stat->public . "\n";
    echo "Aggr Foreigner: " . $stat->foreigner . "\n";
    echo "✅ Aggregation ran successfully\n";
} catch (\Exception $e) {
    echo "❌ Aggregation Failed: " . $e->getMessage() . "\n";
}
