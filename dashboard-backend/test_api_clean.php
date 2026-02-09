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

$response = $controller->getServiceTrends($request);
$data = $response;

echo "Labels: " . implode(", ", $data['labels']) . "\n";
foreach ($data['datasets'] as $ds) {
    echo "Dataset [" . $ds['label'] . "]: " . implode(", ", $ds['data']) . "\n";
}
