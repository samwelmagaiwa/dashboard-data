<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$failed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->first();
if ($failed) {
    echo "ID: {$failed->id}\n";
    $payload = json_decode($failed->payload, true);
    echo "Job Name: " . ($payload['displayName'] ?? 'Unknown') . "\n";
    echo "Connection: " . ($failed->connection ?? 'Unknown') . "\n";
    echo "Queue: " . ($failed->queue ?? 'Unknown') . "\n";
} else {
    echo "No failed jobs found.\n";
}
