<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$user = env('DASHBOARD_API_USERNAME');
$pass = env('DASHBOARD_API_PASSWORD');
$date = '20260201';
$url = "http://192.168.235.250/labsms/swagger/dashboard/{$date}";

echo "Fetching from: {$url}\n";

$response = Http::withBasicAuth($user, $pass)
    ->connectTimeout(30)
    ->timeout(300)
    ->get($url);

if ($response->successful()) {
    $data = $response->json();
    echo "Keys in response: " . implode(', ', array_keys($data)) . "\n";
    if (isset($data['data'])) {
        echo "Data count: " . count($data['data']) . "\n";
    }
    
    file_put_contents('api_sample.json', json_encode($data, JSON_PRETTY_PRINT));
    echo "Sample saved to api_sample.json\n";
} else {
    echo "Request failed: " . $response->status() . "\n";
    echo $response->body();
}
