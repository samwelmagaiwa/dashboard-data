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
        
        $this->info("🔄 Auto-syncing data for {$today}...");
        Log::info("[AutoSync] Starting auto-sync for {$today}");
        
        try {
            // Sync today's data
            $syncService->syncDateRange($today, $today);
            
            $this->info("✅ Auto-sync completed successfully!");
            Log::info("[AutoSync] Completed successfully for {$today}");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Auto-sync failed: {$e->getMessage()}");
            Log::error("[AutoSync] Failed for {$today}: {$e->getMessage()}");
            
            return Command::FAILURE;
        }
    }
}
