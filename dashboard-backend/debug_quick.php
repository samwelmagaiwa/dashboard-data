<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$hasIndex = DB::select("SHOW INDEX FROM visits WHERE Column_name = 'visit_date'");
echo $hasIndex ? "visit_date IS INDEXED\n" : "visit_date NOT INDEXED\n";

$failed = DB::table('sync_logs')->where('status', 'FAILED')->latest()->first();
if ($failed) {
    echo "Last Failure: ID {$failed->id}, Date {$failed->sync_date}, Error: {$failed->error_message}\n";
} else {
    echo "No failed logs found.\n";
}
