<?php
// Direct API check for today's data
require 'dashboard-backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/dashboard-backend');
$dotenv->load();

$date = $argv[1] ?? date('Ymd');
$url = "http://10.20.20.186/emr_visit_api/get_visit_data.php?date={$date}";
$username = $_ENV['DASHBOARD_API_USERNAME'];
$password = $_ENV['DASHBOARD_API_PASSWORD'];

echo "Fetching data for {$date} from {$url}...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$output = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200) {
    die("Error: API returned status $status\n");
}

$data = json_decode($output, true);
$visits = $data['data'] ?? [];

echo "Total visits in API: " . count($visits) . "\n";

$statuses = [];
$visitStatuses = [];
$consTimes = [];

foreach ($visits as $v) {
    // Check 'status'
    $s = $v['status'] ?? 'NULL';
    if ($s === '') $s = 'EMPTY';
    @$statuses[$s]++;

    // Check 'visitStatus'
    $vs = $v['visitStatus'] ?? 'NULL';
    if ($vs === '') $vs = 'EMPTY';
    @$visitStatuses[$vs]++;

    // Check 'consTime' (Consultation Time)
    $ct = $v['consTime'] ?? 'NULL';
    $ct_state = ($ct === 'NULL' || $ct === '' || $ct === '00:00:00') ? 'EMPTY' : 'FILLED';
    @$consTimes[$ct_state]++;
}

echo "Field: status Distribution:\n";
print_r($statuses);

echo "\nField: visitStatus Distribution:\n";
print_r($visitStatuses);

echo "\nField: consTime (State) Distribution:\n";
print_r($consTimes);

// Check for any record where visitStatus is NOT 'C'
$other = array_filter($visits, function($v) {
    return ($v['visitStatus'] ?? '') !== 'C';
});

if (count($other) > 0) {
    echo "\nSamples of non-'C' visits:\n";
    print_r(array_slice($other, 0, 5));
} else {
    echo "\nNO non-'C' visits found in raw API data.\n";
}
