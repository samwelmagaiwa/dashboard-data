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
    public $timeout = 600; // 10 minutes per job to be safe

    public function __construct($date)
    {
        $this->date = $date;
        $this->onQueue('default');
    }

    public function handle(SyncService $syncService)
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $syncService->syncForDateOptimized($this->date);
    }
}
