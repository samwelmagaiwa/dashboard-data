<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$indexes = DB::select('SHOW INDEX FROM visits');
echo "--- INDEXES ---\n";
foreach ($indexes as $i) {
    echo "Name: {$i->Key_name} | Column: {$i->Column_name}\n";
}
