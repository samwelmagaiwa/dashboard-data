<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class SyncWatcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:watch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lightweight monitor to detect new records in the external API and trigger sync';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Sync Watcher STARTED. Monitoring HIS API every 5s...");
        $this->info("Press Ctrl+C to stop.");

        // Loop indefinitely for "Immediate" sync experience
        while(true) {
            $today = Carbon::today()->format('Y-m-d');
            $todayYmd = Carbon::today()->format('Ymd');
            $baseUrl = env('DASHBOARD_API_BASE_URL', 'http://192.168.235.250/labsms/swagger/dashboard');
            $url = "{$baseUrl}/{$todayYmd}";

            try {
                // 1. Get local count for today
                $localCount = Visit::where('visit_date', $today)->count();

                // 2. Poll Throttle - don't hit HIS more than once every 5 seconds
                $lastPollKey = "sync_watcher_last_poll_{$today}";
                $lastPollValue = Cache::get($lastPollKey);
                $lastSyncKey = "sync_watcher_last_sync_{$today}";
                $lastSyncValue = Cache::get($lastSyncKey);
                
                $secondsSincePoll = $lastPollValue ? abs(now()->diffInSeconds($lastPollValue)) : 999;
                $secondsSinceSync = $lastSyncValue ? abs(now()->diffInSeconds($lastSyncValue)) : 999;
                
                // Only show output if something interesting happens or every 30s to avoid spam
                if ($secondsSincePoll >= 30) {
                    $this->line("[" . now()->toTimeString() . "] ⏱️ Seconds since: Poll={$secondsSincePoll}s, Sync={$secondsSinceSync}s (Local: {$localCount})");
                }

                if ($secondsSincePoll >= 5) {
                    // 3. Get external count
                    $username = env('DASHBOARD_API_USERNAME');
                    $password = env('DASHBOARD_API_PASSWORD');

                    $response = Http::withBasicAuth($username, $password)
                        ->connectTimeout(5)
                        ->timeout(15)
                        ->get($url);

                    if ($response->successful()) {
                        $data = $response->json();
                        $externalCount = count($data['data'] ?? []);
                        
                        // Record the poll time
                        Cache::put($lastPollKey, now(), now()->addMinutes(10));

                        if ($externalCount > $localCount) {
                            $diff = $externalCount - $localCount;
                            $this->info("🚩 [" . now()->toTimeString() . "] NEW DATA DETECTED! External: {$externalCount}, Local: {$localCount}. Syncing {$diff} new records...");
                            Log::info("[SyncWatcher] New records found ({$diff}) for {$today}. Triggering sync.");
                            
                            // Trigger the existing sync command
                            Artisan::call('sync:auto-daily');
                            
                            // Record sync trigger to prevent immediate re-trigger
                            Cache::put("sync_watcher_last_sync_{$today}", now(), now()->addMinutes(10));
                            Cache::put("sync_watcher_last_local_sent_{$today}", $localCount, now()->addMinutes(10));
                            
                            $this->info("✅ Sync dispatched.");
                        }
                    } else {
                        $this->error("❌ External API unreachable: " . $response->status());
                    }
                }

            } catch (\Exception $e) {
                $this->error("❌ Watcher failed: " . $e->getMessage());
                Log::error("[SyncWatcher] Error: " . $e->getMessage());
            }

            // Sleep for 5 seconds before next check
            sleep(5);
        }

        return Command::SUCCESS;
    }
}
