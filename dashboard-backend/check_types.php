<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$visit_types = DB::select("
    SELECT visit_type, COUNT(*) as cnt
    FROM visits
    GROUP BY visit_type
");

echo "--- VISIT TYPES FOUND ---\n";
foreach ($visit_types as $v) {
    echo "Type: {$v->visit_type} | Count: {$v->cnt}\n";
}
