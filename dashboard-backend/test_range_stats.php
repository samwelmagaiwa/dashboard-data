<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Http\Request;

$request = new Request([
    'start_date' => '2026-01-05',
    'end_date' => '2026-01-30'
]);

$controller = app(DashboardController::class);
$response = $controller->getStats($request);

echo $response->getContent();
