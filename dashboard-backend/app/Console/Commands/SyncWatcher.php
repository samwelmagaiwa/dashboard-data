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
        $today = Carbon::today()->format('Y-m-d');
        $todayYmd = Carbon::today()->format('Ymd');
        $baseUrl = env('DASHBOARD_API_BASE_URL', 'http://192.168.235.250/labsms/swagger/dashboard');
        $url = "{$baseUrl}/{$todayYmd}";

        try {
            // 1. Get local count for today
            $localCount = Visit::where('visit_date', $today)->count();

            // 2. Poll Throttle - don't hit HIS more than once every 15 seconds
            $lastPollKey = "sync_watcher_last_poll_{$today}";
            $lastPollValue = Cache::get($lastPollKey);
            $lastSyncKey = "sync_watcher_last_sync_{$today}";
            $lastSyncValue = Cache::get($lastSyncKey);
            
            $secondsSincePoll = $lastPollValue ? abs(now()->diffInSeconds($lastPollValue)) : 999;
            $secondsSinceSync = $lastSyncValue ? abs(now()->diffInSeconds($lastSyncValue)) : 999;
            
            $this->info("⏱️ Seconds since: Poll={$secondsSincePoll}s, Sync={$secondsSinceSync}s (Target: Poll>15s, Sync>60s if same count)");

            if ($secondsSincePoll < 15) {
                if ($secondsSinceSync < 60 && Cache::get("sync_watcher_last_local_sent_{$today}") === $localCount) {
                     $this->info("😴 Throttling active. Local state: {$localCount}");
                     return Command::SUCCESS;
                }
            }

            // 3. Get external count
            $username = env('DASHBOARD_API_USERNAME');
            $password = env('DASHBOARD_API_PASSWORD');

            $this->info("📡 Polling HIS API for {$today}...");
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
                    $this->info("� NEW DATA DETECTED! External: {$externalCount}, Local: {$localCount}. Syncing {$diff} new records...");
                    Log::info("[SyncWatcher] New records found ({$diff}) for {$today}. Triggering sync.");
                    
                    // Trigger the existing sync command
                    Artisan::call('sync:auto-daily');
                    
                    // Record sync trigger to prevent immediate re-trigger
                    Cache::put("sync_watcher_last_sync_{$today}", now(), now()->addMinutes(10));
                    Cache::put("sync_watcher_last_local_sent_{$today}", $localCount, now()->addMinutes(10));
                    
                    $this->info("✅ Sync dispatched.");
                } else {
                    $this->info("😴 Up to date. Local: {$localCount}, External: {$externalCount}");
                }
            } else {
                $this->error("❌ External API unreachable: " . $response->status());
                Log::warning("[SyncWatcher] External API returned status: " . $response->status());
            }

        } catch (\Exception $e) {
            $this->error("❌ Watcher failed: " . $e->getMessage());
            Log::error("[SyncWatcher] Error: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
