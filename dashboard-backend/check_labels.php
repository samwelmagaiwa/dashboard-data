<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$date = '2026-02-09';
$phpLabel = Carbon::parse($date)->format('l');
$mysqlLabel = DB::selectOne("SELECT DATE_FORMAT(?, '%W') as group_key", [$date])->group_key;

echo "PHP Label: [$phpLabel]\n";
echo "MySQL Label: [$mysqlLabel]\n";

if ($phpLabel === $mysqlLabel) {
    echo "MATCH!\n";
} else {
    echo "MISMATCH!\n";
}
