<?php

namespace App\Jobs;

use App\Services\SyncService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReaggregateRangeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, string> */
    public array $dates;
    public int $timeout = 600;
    public int $tries = 2;

    /**
     * @param array<int, string> $dates
     */
    public function __construct(array $dates)
    {
        $this->dates = array_values($dates);

        if (!$this->queue) {
            $this->onQueue('default');
        }
    }

    public function handle(SyncService $syncService): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        foreach ($this->dates as $date) {
            if ($this->batch() && $this->batch()->cancelled()) {
                return;
            }

            $syncService->updateAggregatedStats($date);
        }
    }
}
