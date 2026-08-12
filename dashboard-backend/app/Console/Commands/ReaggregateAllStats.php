<?php

namespace App\Console\Commands;

use App\Jobs\ReaggregateRangeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class ReaggregateAllStats extends Command
{
    protected $signature = 'dashboard:reaggregate
                            {--start= : Start date (Y-m-d), defaults to earliest visit}
                            {--end=   : End date (Y-m-d), defaults to today}
                            {--chunk=14 : Dates per job}';

    protected $description = 'Re-aggregate all daily_dashboard_stats from raw visits. Run after any change to the consulted/pending logic.';

    public function handle(): int
    {
        $start = $this->option('start')
            ?? DB::table('visits')->min(DB::raw('DATE(visit_date)'));
        $end   = $this->option('end') ?? now()->toDateString();
        $chunk = (int) $this->option('chunk');

        if (!$start) {
            $this->error('No visits found in the database.');
            return 1;
        }

        $dates = DB::table('visits')
            ->selectRaw('DATE(visit_date) as d')
            ->whereBetween(DB::raw('DATE(visit_date)'), [$start, $end])
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('d')
            ->toArray();

        if (empty($dates)) {
            $this->info('No dates to re-aggregate in the given range.');
            return 0;
        }

        $jobs = array_map(
            fn($c) => new ReaggregateRangeJob($c),
            array_chunk($dates, $chunk)
        );

        $batch = Bus::batch($jobs)
            ->name('reaggregate:' . $start . ':' . $end)
            ->dispatch();

        $this->info(sprintf(
            'Dispatched %d jobs for %d dates (%s → %s). Batch ID: %s',
            count($jobs), count($dates), $start, $end, $batch->id
        ));

        return 0;
    }
}
