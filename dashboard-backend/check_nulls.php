<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$null_checks = DB::select("
    SELECT 
        SUM(CASE WHEN clinic_code IS NULL THEN 1 ELSE 0 END) as null_clinic_code,
        SUM(CASE WHEN dept_code IS NULL THEN 1 ELSE 0 END) as null_dept_code,
        SUM(CASE WHEN mr_number IS NULL THEN 1 ELSE 0 END) as null_mr,
        COUNT(*) as total_records
    FROM visits
");

echo "--- NULL FIELD CHECKS ---\n";
print_r($null_checks[0]);
