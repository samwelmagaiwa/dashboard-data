<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2026-02-09';
$mysqlLabel = DB::selectOne("SELECT DATE_FORMAT(?, '%W') as group_key", [$date])->group_key;

echo "Label: [$mysqlLabel]\n";
echo "Length: " . strlen($mysqlLabel) . "\n";
echo "Hex: " . bin2hex($mysqlLabel) . "\n";
