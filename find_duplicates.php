<?php
require 'dashboard-backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/dashboard-backend');
$dotenv->load();

$date = '20260202';
$url = "http://192.168.235.250/labsms/swagger/dashboard/{$date}";
$username = $_ENV['DASHBOARD_API_USERNAME'];
$password = $_ENV['DASHBOARD_API_PASSWORD'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
$output = curl_exec($ch);
curl_close($ch);

$data = json_decode($output, true);
$visits = $data['data'] ?? [];

$seen = [];
$duplicates = [];

foreach ($visits as $v) {
    $key = $v['mrNumber'] . '|' . $v['visitNum'] . '|' . $v['visitDate'];
    if (isset($seen[$key])) {
        if (!isset($duplicates[$key])) {
            $duplicates[$key] = [$seen[$key]];
        }
        $duplicates[$key][] = $v;
    } else {
        $seen[$key] = $v;
    }
}

echo "Total records in API: " . count($visits) . "\n";
echo "Unique keys (mr|num|date): " . count($seen) . "\n";
echo "Keys with multiple entries: " . count($duplicates) . "\n\n";

if (count($duplicates) > 0) {
    echo "Examples of duplicates:\n";
    $i = 0;
    foreach ($duplicates as $key => $items) {
        if ($i++ > 3) break;
        echo "Key: $key\n";
        foreach ($items as $item) {
            echo "  - Clinic: " . ($item['clinicName'] ?? 'N/A') . " | Dept: " . ($item['deptName'] ?? 'N/A') . " | Status: " . ($item['visitStatus'] ?? 'N/A') . "\n";
        }
    }
}
