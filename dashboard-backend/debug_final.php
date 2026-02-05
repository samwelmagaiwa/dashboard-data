<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "FAILED Sync Logs (Full Errors):\n";
    $logs = DB::table('sync_logs')->where('status', 'FAILED')->latest()->take(3)->get();
    foreach ($logs as $log) {
        echo "ID: {$log->id}, Date: {$log->sync_date}, Error: {$log->error_message}\n";
    }
    
    echo "\nIndexes on visits table:\n";
    $indexes = DB::select('SHOW INDEX FROM visits');
    $indexedCols = [];
    foreach ($indexes as $idx) {
        $indexedCols[] = $idx->Column_name;
        echo "Index: {$idx->Key_name}, Column: {$idx->Column_name}\n";
    }
    
    if (in_array('visit_date', $indexedCols)) {
        echo "\nvisit_date IS indexed.\n";
    } else {
        echo "\nvisit_date IS NOT indexed! This is likely the cause of slow aggregation.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
