<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Find cases where someone might have two consultations in the same clinic/dept
// But different cons_no or different doctors
$potential_missed = DB::select("
    SELECT mr_number, visit_date, clinic_code, dept_code, COUNT(DISTINCT cons_no) as cons_count, COUNT(*) as total_rows
    FROM visits
    GROUP BY mr_number, visit_date, clinic_code, dept_code
    HAVING total_rows > 1
    LIMIT 20
");

echo "--- POTENTIAL MULTIPLE VISITS IN SAME CLINIC ---\n";
foreach ($potential_missed as $m) {
    echo "MR: {$m->mr_number} | Date: {$m->visit_date} | Clinic: {$m->clinic_code} | Dept: {$m->dept_code} | Cons Count: {$m->cons_count} | Total Rows: {$m->total_rows}\n";
}
