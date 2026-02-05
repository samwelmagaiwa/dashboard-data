<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$emergency_depts = DB::select("
    SELECT DISTINCT dept_code, dept_name
    FROM visits
    WHERE dept_name LIKE '%Emergency%' 
       OR dept_name LIKE '%Casualty%'
       OR dept_name LIKE '%ER%'
");

echo "--- EMERGENCY DEPARTMENTS FOUND ---\n";
foreach ($emergency_depts as $d) {
    echo "Code: {$d->dept_code} | Name: {$d->dept_name}\n";
}
