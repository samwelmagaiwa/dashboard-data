<?php

namespace App\Jobs;

use App\Services\SyncService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $dates;
    public $timeout = 900; // 15 minutes for 5 dates

    public function __construct(array $dates)
    {
        $this->dates = $dates;
        $this->onQueue('default');
    }

    public function handle(SyncService $syncService)
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        // Process these specific dates in parallel
        $result = $syncService->syncDateRange(min($this->dates), max($this->dates));
        
        // Note: syncDateRange already handles individual successes/failures
        // We consider the job successful if it finished processing the chunk
    }
}
