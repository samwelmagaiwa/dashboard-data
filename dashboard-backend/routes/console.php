<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sync:auto-daily')->everyTenMinutes();

Artisan::command('dashboard:refresh-duplicate-occurrences {date}', function () {
    $date = $this->argument('date');
    $service = app(\App\Services\SyncService::class);
    $service->refreshDuplicateOccurrences($date);
    $this->info("Refreshed duplicate occurrences for {$date}");
})->purpose('Refresh duplicate occurrence counts for a specific date');
