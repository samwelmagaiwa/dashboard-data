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
        
        foreach ($datesToSync as $date) {
            $this->info("🚀 Dispatching auto-sync job for {$date} to queue...");
            Log::info("[AutoSync] Dispatching sync job for {$date}");
            
            try {
                // Dispatch to queue to leverage the 3 active workers
                \App\Jobs\SyncForDateJob::dispatch($date)->onQueue('default');
                $this->info("✅ Job for {$date} dispatched successfully!");
            } catch (\Exception $e) {
                $this->error("❌ Failed to dispatch for {$date}: {$e->getMessage()}");
                Log::error("[AutoSync] Dispatch failed for {$date}: {$e->getMessage()}");
            }
        }
        
        return Command::SUCCESS;
    }
}
