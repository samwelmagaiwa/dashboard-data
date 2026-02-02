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

$keys = [];
foreach ($visits as $v) {
    $key = $v['mrNumber'] . '|' . $v['visitNum'] . '|' . $v['visitDate'] . '|' . ($v['clinicCode'] ?? 'NULL');
    $keys[$key] = true;
}

echo "Total records in API: " . count($visits) . "\n";
echo "Granular unique keys (mr|num|date|clinic): " . count($keys) . "\n";
