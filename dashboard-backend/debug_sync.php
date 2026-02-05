<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Checking Sync Logs:\n";
    $logs = DB::table('sync_logs')->latest()->take(5)->get();
    foreach ($logs as $log) {
        echo "ID: {$log->id}, Date: {$log->sync_date}, Status: {$log->status}, Error: {$log->error_message}\n";
    }
    
    echo "\nChecking Database Stats:\n";
    $count = DB::table('visits')->count();
    echo "Total visits: $count\n";

} catch (\Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
