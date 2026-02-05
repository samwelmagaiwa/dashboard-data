<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$cons_check = DB::select("
    SELECT 
        SUM(CASE WHEN cons_no IS NULL THEN 1 ELSE 0 END) as null_cons,
        COUNT(*) as total
    FROM visits
");

echo "--- CONS_NO NULL CHECK ---\n";
print_r($cons_check[0]);
