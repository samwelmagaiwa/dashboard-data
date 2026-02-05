<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Visit;

echo "--- ALL CATEGORIES --- \n";
$cats = Visit::distinct()->pluck('pat_catg_nm');
foreach ($cats as $c) {
    echo " - $c\n";
}
