<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyDashboardStat;
use Carbon\Carbon;

$stats = DailyDashboardStat::orderBy('stat_date', 'desc')->take(30)->get();

echo "Date | Weekday | Visits\n";
echo "--------------------------\n";
foreach ($stats as $s) {
    echo $s->stat_date . " | " . Carbon::parse($s->stat_date)->format('l') . " | " . $s->total_visits . "\n";
}
