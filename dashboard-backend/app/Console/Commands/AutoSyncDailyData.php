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
        
        $this->info("🚀 Dispatching auto-sync job for {$today} to queue...");
        Log::info("[AutoSync] Dispatching sync job for {$today}");
        
        try {
            // Dispatch to queue to leverage the 3 active workers
            \App\Jobs\SyncForDateJob::dispatch($today)->onQueue('default');
            
            $this->info("✅ Job dispatched successfully!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to dispatch: {$e->getMessage()}");
            Log::error("[AutoSync] Dispatch failed for {$today}: {$e->getMessage()}");
            
            return Command::FAILURE;
        }
    }
}
