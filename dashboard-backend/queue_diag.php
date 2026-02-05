<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- QUEUE STATUS ---\n";
$failedCount = DB::table('failed_jobs')->count();
echo "Failed jobs count: {$failedCount}\n";

$batches = DB::table('job_batches')->orderByDesc('created_at')->limit(5)->get();
echo "--- BATCH STATUS (Last 5) ---\n";
foreach ($batches as $b) {
    $progress = $b->total_jobs > 0 ? (1 - ($b->pending_jobs / $b->total_jobs)) * 100 : 0;
    echo "ID: {$b->id} | Total: {$b->total_jobs} | Pending: {$b->pending_jobs} | Failed: {$b->failed_jobs} | Progress: " . round($progress, 2) . "%\n";
}

echo "\n--- RECENT SYNC LOGS ---\n";
$logs = \App\Models\SyncLog::orderByDesc('id')->limit(10)->get();
foreach ($logs as $l) {
    echo "Date: {$l->sync_date} | Status: {$l->status} | Count: {$l->records_synced} | Error: {$l->error_message}\n";
}
