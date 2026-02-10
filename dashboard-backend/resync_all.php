<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check current data range
$stats = DB::selectOne("SELECT MIN(visit_date) as min_date, MAX(visit_date) as max_date, COUNT(*) as total FROM visits");
echo "Current data:\n";
echo "  Date range: {$stats->min_date} to {$stats->max_date}\n";
echo "  Total visits: {$stats->total}\n\n";

// Check sample of duplicate scenarios (same patient, same clinic, same day, different cons_no)
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
