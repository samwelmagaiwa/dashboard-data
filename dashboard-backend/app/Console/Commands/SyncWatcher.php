<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $url = "http://192.168.235.250/labsms/swagger/dashboard/{$todayYmd}";

        try {
            // 1. Get local count for today
            $localCount = Visit::whereDate('visit_date', $today)->count();

            // 2. Get external count (lightweight call)
            $username = env('DASHBOARD_API_USERNAME');
            $password = env('DASHBOARD_API_PASSWORD');

            $response = Http::withBasicAuth($username, $password)
                ->connectTimeout(5)
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $externalCount = count($data['data'] ?? []);

                if ($externalCount > $localCount) {
                    $diff = $externalCount - $localCount;
                    $this->info("🔍 New data detected! External: {$externalCount}, Local: {$localCount}. Diff: {$diff}");
                    Log::info("[SyncWatcher] New records found ({$diff}). Triggering auto-sync.");
                    
                    // Trigger the existing sync command
                    Artisan::call('sync:auto-daily');
                    
                    $this->info("✅ Sync triggered successfully.");
                } else {
                    $this->info("😴 No new records. Local: {$localCount}, External: {$externalCount}");
                }
            } else {
                $this->error("❌ External API unreachable: " . $response->status());
            }

        } catch (\Exception $e) {
            $this->error("❌ Watcher failed: " . $e->getMessage());
            Log::error("[SyncWatcher] Error: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
