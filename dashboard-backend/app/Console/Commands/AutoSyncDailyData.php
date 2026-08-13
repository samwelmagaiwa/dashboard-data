<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SyncService;
use Illuminate\Support\Facades\Log;

class AutoSyncDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:auto-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically sync today\'s data from external API';

    /**
     * Execute the console command.
     */
    public function handle(SyncService $syncService)
    {
        $batchName = 'auto-sync:daily';

        // Cancel zombie batches: active by name but with no real jobs left in the queue.
        // These occur when SyncForDateJob completes cleanly without marking the batch done
        // (e.g. circuit-open skips) or when a worker died mid-job.
        $zombieThreshold = now()->subMinutes(15)->getTimestamp();
        \Illuminate\Support\Facades\DB::table('job_batches')
            ->where('name', $batchName)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->where('created_at', '<', $zombieThreshold)
            ->update(['cancelled_at' => now()->getTimestamp(), 'finished_at' => now()->getTimestamp()]);

        // Prevent duplicate active daily syncs (after zombie cleanup)
        $existing = \Illuminate\Support\Facades\DB::table('job_batches')
            ->where('name', $batchName)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->exists();

        if ($existing) {
            $this->warn("An active auto-sync is already in progress. Skipping...");
            return Command::SUCCESS;
        }

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        $jobs = [
            new \App\Jobs\SyncForDateJob($today),
            new \App\Jobs\SyncForDateJob($yesterday),
        ];

        \Illuminate\Support\Facades\Bus::batch($jobs)
            ->name($batchName)
            ->dispatch();

        $this->info("Auto-sync batch dispatched for {$today} and {$yesterday}.");

        return Command::SUCCESS;
    }
}
