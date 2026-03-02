<?php

namespace App\Jobs;

use App\Services\SyncService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncForDateJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $date;
    public $force;
    public $timeout = 600; // 10 minutes per job to be safe

    public function __construct($date, $force = false)
    {
        $this->date = $date;
        $this->force = $force;
        $this->onQueue('default');
    }

    public function handle(SyncService $syncService)
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        // Skip if already synced successfully and not forced
        // EXCEPT for today and yesterday, where data might still be streaming in
        if (!$this->force) {
            $isStreamingDate = $this->date === now()->format('Y-m-d') || $this->date === now()->subDay()->format('Y-m-d');
            
            if (!$isStreamingDate) {
                $exists = \App\Models\SyncLog::where('sync_date', $this->date)
                    ->where('status', 'SUCCESS')
                    ->exists();
                
                if ($exists) {
                    return;
                }
            }
        }

        $result = $syncService->syncForDateOptimized($this->date);
        
        if (!$result['success']) {
            throw new \Exception($result['error'] ?? 'Sync failed');
        }
    }
}
