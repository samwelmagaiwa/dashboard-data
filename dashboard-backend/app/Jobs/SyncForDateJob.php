<?php

namespace App\Jobs;

use App\Services\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncForDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $date;

    /**
     * @param string $date Date in Y-m-d format
     */
    public function __construct(string $date)
    {
        $this->date = $date;
    }

    public function handle(SyncService $syncService): void
    {
        // syncForDate() also updates aggregated tables.
        $syncService->syncForDate($this->date);
    }
}
