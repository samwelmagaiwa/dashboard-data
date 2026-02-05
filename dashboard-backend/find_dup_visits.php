<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$duplicates = DB::select("
    SELECT mr_number, visit_num, visit_date, clinic_code, dept_code, COUNT(*) as cnt
    FROM visits
    GROUP BY mr_number, visit_num, visit_date, clinic_code, dept_code
    HAVING cnt > 1
    LIMIT 10
");

echo "--- DUPLICATES FOUND ---\n";
foreach ($duplicates as $d) {
    echo "MR: {$d->mr_number} | Num: {$d->visit_num} | Date: {$d->visit_date} | Clinic: {$d->clinic_code} | Dept: {$d->dept_code} | Count: {$d->cnt}\n";
}
