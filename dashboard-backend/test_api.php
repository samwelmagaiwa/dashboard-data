<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Http\Request;

$controller = app(DashboardController::class);
$request = new Request([
    'period' => 'day',
    'start_date' => '2026-02-09',
    'end_date' => '2026-02-09'
]);

$startDate = '2026-02-09';
$endDate = '2026-02-09';
$period = 'day';

$start = Carbon\Carbon::parse($startDate);
$startDateExpanded = $start->copy()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();
$endDateExpanded = $start->copy()->endOfWeek(Carbon\Carbon::SUNDAY)->toDateString();

echo "Expanded range: $startDateExpanded to $endDateExpanded\n";

$results = \App\Models\DailyDashboardStat::whereDate('stat_date', '>=', $startDateExpanded)
    ->whereDate('stat_date', '<=', $endDateExpanded)
    ->selectRaw('DATE_FORMAT(stat_date, "%W") as group_key, SUM(total_visits) as opd')
    ->groupBy('group_key')
    ->get();

echo "Raw Results:\n";
foreach ($results as $row) {
    echo "Key: [{$row->group_key}], OPD: {$row->opd}\n";
}

$response = $controller->getServiceTrends($request);
echo "API Response:\n";
echo json_encode($response, JSON_PRETTY_PRINT);
