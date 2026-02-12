<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$date = $argv[1] ?? date('Y-m-d');

// Count in visits table
$visitCount = DB::table('visits')->whereDate('visit_date', $date)->count();
echo "Visits table count for {$date}: {$visitCount}\n";

// Count in daily_dashboard_stats
$stat = DB::table('daily_dashboard_stats')->where('stat_date', $date)->first();
echo "Dashboard stats total_visits: " . ($stat->total_visits ?? 'N/A') . "\n";

// Check for any NULL cons_no
$nullConsNo = DB::table('visits')->whereDate('visit_date', $date)->whereNull('cons_no')->count();
echo "Visits with NULL cons_no: {$nullConsNo}\n";

// Check unique combinations
$uniqueKeys = DB::table('visits')
    ->whereDate('visit_date', $date)
    ->select(DB::raw('COUNT(DISTINCT CONCAT(mr_number, visit_num, clinic_code, dept_code, cons_no)) as unique_combos'))
    ->first();
echo "Unique key combinations: {$uniqueKeys->unique_combos}\n";
