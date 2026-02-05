<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SyncLog;

echo "--- FAILED SYNC TIMES ---\n";
$logs = SyncLog::where('status', 'FAILED')->orderBy('id', 'desc')->limit(5)->get();
foreach ($logs as $l) {
    echo "Date: {$l->sync_date} | Start: {$l->started_at} | End: {$l->finished_at} | Error: {$l->error_message}\n";
}
