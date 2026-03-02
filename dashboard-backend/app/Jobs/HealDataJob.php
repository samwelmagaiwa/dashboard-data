<?php

namespace App\Jobs;

use App\Services\SyncService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HealDataJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $date;
    public $timeout = 1800; // 30 minutes for deep healing

    public function __construct($date = null)
    {
        $this->date = $date;
        $this->onQueue('default');
    }

    public function handle(SyncService $syncService)
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        Log::info("[HealDataJob] Starting data healing for " . ($this->date ?: 'all dates'));
        $count = $syncService->healMissingData($this->date);
        Log::info("[HealDataJob] Successfully healed $count records.");
    }
}
