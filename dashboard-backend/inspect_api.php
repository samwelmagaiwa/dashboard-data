<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$date = '20260302';
$baseUrl = env('DASHBOARD_API_BASE_URL');
$url = "{$baseUrl}/{$date}";
$username = env('DASHBOARD_API_USERNAME');
$password = env('DASHBOARD_API_PASSWORD');

$response = Http::withBasicAuth($username, $password)->get($url);
$data = $response->json();

echo "Keys in response: " . implode(', ', array_keys($data)) . "\n";
if (isset($data['total'])) echo "Total from API: " . $data['total'] . "\n";
if (isset($data['count'])) echo "Count from API: " . $data['count'] . "\n";
echo "Data array count: " . count($data['data'] ?? []) . "\n";
if (isset($data['data'][0])) echo "Sample record structure: " . implode(', ', array_keys($data['data'][0])) . "\n";
