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
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        
        $datesToSync = [$today, $yesterday];
        $jobs = [];
        foreach ($datesToSync as $date) {
            $jobs[] = new \App\Jobs\SyncForDateJob($date);
        }

        if (!empty($jobs)) {
            $batchName = 'auto-sync:daily';
            
            // Prevent duplicate active daily syncs
            $existing = \Illuminate\Support\Facades\DB::table('job_batches')
                ->where('name', $batchName)
                ->whereNull('finished_at')
                ->whereNull('cancelled_at')
                ->exists();
                
            if ($existing) {
                $this->warn("⚠️  An active auto-sync is already in progress. Skipping...");
                return Command::SUCCESS;
            }

            \Illuminate\Support\Facades\Bus::batch($jobs)
                ->name($batchName)
                ->dispatch();
            
            $this->info("✅ Auto-sync batch dispatched successfully!");
        }
        
        return Command::SUCCESS;
    }
}
