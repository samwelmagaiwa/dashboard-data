<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- RECENT FAILED JOBS ---\n";
$failed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(5)->get();
foreach ($failed as $f) {
    echo "ID: {$f->id} | Queue: {$f->queue} | Time: {$f->failed_at}\n";
    echo "Exception: " . substr($f->exception, 0, 500) . "...\n\n";
}
