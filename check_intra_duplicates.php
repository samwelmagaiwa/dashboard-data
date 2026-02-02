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
    $key = $v['mrNumber'] . '|' . $v['visitNum'] . '|' . $v['visitDate'] . '|' . ($v['clinicCode'] ?? 'NULL');
    if (isset($seen[$key])) {
        $duplicates[] = [
            'key' => $key,
            'existing' => $seen[$key],
            'new' => $v
        ];
    } else {
        $seen[$key] = $v;
    }
}

echo "Duplicates found within the same clinic: " . count($duplicates) . "\n\n";

foreach ($duplicates as $d) {
    echo "Duplicate Key: " . $d['key'] . "\n";
    echo "  Entry 1: ConsTime: " . ($d['existing']['consTime'] ?? 'N/A') . " | Status: " . ($d['existing']['visitStatus'] ?? 'N/A') . "\n";
    echo "  Entry 2: ConsTime: " . ($d['new']['consTime'] ?? 'N/A') . " | Status: " . ($d['new']['visitStatus'] ?? 'N/A') . "\n";
    echo "-------------------\n";
}
