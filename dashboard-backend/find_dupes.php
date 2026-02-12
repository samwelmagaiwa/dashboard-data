<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$date = $argv[1] ?? date('Y-m-d');
$dateYmd = \Carbon\Carbon::parse($date)->format('Ymd');

$baseUrl = env('DASHBOARD_API_BASE_URL', 'http://192.168.235.250/labsms/swagger/dashboard');
$url = "{$baseUrl}/{$dateYmd}";

$response = Http::withBasicAuth(env('DASHBOARD_API_USERNAME'), env('DASHBOARD_API_PASSWORD'))
    ->timeout(60)
    ->get($url);

$data = $response->json();
$visits = $data['data'] ?? [];

echo "API returned " . count($visits) . " visits for {$date}\n\n";

// Build unique key map
$keyMap = [];
foreach ($visits as $index => $v) {
    $consNo = $v['consNo'] ?? null;
    if (empty($consNo)) {
        $consTime = $v['consTime'] ?? null;
        $timePart = $consTime ? str_replace(':', '', $consTime) : $index;
        $consNo = ($v['visitNum'] ?? '') . '-' . $timePart;
    }
    
    $key = implode('|', [
        $v['mrNumber'] ?? '',
        $v['visitNum'] ?? '',
        $v['visitDate'] ?? '',
        $v['clinicCode'] ?? '',
        $v['deptCode'] ?? '',
        $consNo
    ]);
    
    if (!isset($keyMap[$key])) {
        $keyMap[$key] = [];
    }
    $keyMap[$key][] = $v;
}

// Find duplicates
$dupes = array_filter($keyMap, fn($items) => count($items) > 1);

echo "Found " . count($dupes) . " duplicate key combinations:\n\n";

foreach ($dupes as $key => $items) {
    echo "Key: {$key}\n";
    echo "  Count: " . count($items) . "\n";
    foreach ($items as $i => $item) {
        echo "  [{$i}] consNo={$item['consNo']}, consTime={$item['consTime']}, doctor={$item['consDoctor']}\n";
    }
    echo "\n";
}
