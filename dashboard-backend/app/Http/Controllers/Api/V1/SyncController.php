<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\SyncLog;
use App\Models\DailyDashboardStat;
use App\Models\ClinicStat;
use App\Services\GapDetectionService;
use App\Services\SyncService;
use App\Jobs\SyncForDateJob;
use App\Jobs\HealDataJob;
use App\Jobs\ReaggregateRangeJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SyncController extends Controller
{
    protected $syncService;
    protected $gapService;

    public function __construct(SyncService $syncService, GapDetectionService $gapService)
    {
        $this->syncService = $syncService;
        $this->gapService = $gapService;
        set_time_limit(0);
    }

    public function sync($date = null)
    {
        // Handle Ymd or Y-m-d
        if ($date && strlen($date) === 8 && is_numeric($date)) {
            $formattedDate = Carbon::createFromFormat('Ymd', $date)->toDateString();
        } else {
            $formattedDate = $date ?: date('Y-m-d');
        }

        $result = $this->syncService->syncForDate($formattedDate);

        if ($result['success']) {
            $sample = Visit::whereDate('visit_date', $formattedDate)->latest()->first();
            return response()->json([
                'message' => "Successfully synced {$result['count']} records for date {$formattedDate}",
                'sample_channeled_data' => $sample
            ]);
        }

        return response()->json([
            'error' => 'Sync failed',
            'details' => $result['error']
        ], 500);
    }

    /**
     * Trigger a background sync for a specific date (default today).
     * Returns immediately while the job runs in the queue.
     */
    public function triggerSync($date = null)
    {
        // Handle Ymd or Y-m-d
        if ($date && strlen($date) === 8 && is_numeric($date)) {
            $formattedDate = Carbon::createFromFormat('Ymd', $date)->toDateString();
        } else {
            $formattedDate = $date ?: date('Y-m-d');
        }

        $batch = Bus::batch([
            new SyncForDateJob($formattedDate, true) // Force true for manual triggers
        ])->name("sync:{$formattedDate}")->dispatch();

        return response()->json([
            'message' => "Sync job dispatched for date {$formattedDate}",
            'status' => 'queued',
            'batch_id' => $batch->id
        ]);
    }

    public function syncRange(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Start date and end date are required'], 400);
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Safety limit: prevent syncing more than 366 days at once via API to avoid timeout issues
        if ($start->diffInDays($end) > 366) {
            return response()->json(['error' => 'Range too large. Please sync max 1 year at a time.'], 400);
        }

        try {
            // Use the optimized parallel sync service
            $result = $this->syncService->syncDateRange($start->toDateString(), $end->toDateString());
            
            return response()->json([
                'message' => "Sync completed for range $startDate to $endDate",
                'synced_days' => $result['total_synced_days'],
                'errors' => $result['errors']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Sync failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rebuild aggregated dashboard tables for a date range based on already-synced `visits`.
     * This is much faster than `syncRange()` because it does NOT call the external API.
     */
    public function reaggregateRange(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Start date and end date are required'], 400);
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Same safety limit as syncing.
        if ($start->diffInDays($end) > 366) {
            return response()->json(['error' => 'Range too large. Please rebuild max 1 year at a time.'], 400);
        }

        $rebuiltDays = 0;
        $errors = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            try {
                $this->syncService->updateAggregatedStats($dateString);
                $rebuiltDays++;
            } catch (\Exception $e) {
                $errors[$dateString] = $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Re-aggregation completed for range $startDate to $endDate",
            'rebuilt_days' => $rebuiltDays,
            'errors' => $errors
        ]);
    }

    /**
     * Queue a background sync of a date range.
     * This avoids HTTP timeouts when syncing large ranges (e.g. an entire year).
     */
    public function enqueueSyncRange(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $force = $request->query('force') === 'true';

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Start date and end date are required'], 400);
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($start->diffInDays($end) > 366) {
            return response()->json(['error' => 'Range too large. Please sync max 1 year at a time.'], 400);
        }

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (!$this->gapService->expectsDataForDate($date)) {
                continue;
            }

            $dates[] = $date->toDateString();
        }

        $visitCounts = Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->selectRaw('visit_date, COUNT(*) as total_visits')
            ->groupBy('visit_date')
            ->pluck('total_visits', 'visit_date');

        $statsByDate = DailyDashboardStat::whereBetween('stat_date', [$startDate, $endDate])
            ->get(['stat_date', 'total_visits'])
            ->keyBy(fn ($item) => $item->stat_date->format('Y-m-d'));

        $syncDates = [];
        $reaggregateDates = [];

        foreach ($dates as $date) {
            if ($force) {
                $syncDates[] = $date;
                continue;
            }

            $rawVisits = (int) ($visitCounts[$date] ?? 0);
            $stat = $statsByDate->get($date);
            $statVisits = $stat ? (int) $stat->total_visits : null;

            if ($rawVisits > 0) {
                if ($statVisits === null || $statVisits !== $rawVisits) {
                    $reaggregateDates[] = $date;
                }

                continue;
            }

            if ($statVisits === null) {
                $syncDates[] = $date;
                continue;
            }

            if ($statVisits > 0) {
                $syncDates[] = $date;
            }
        }

        $syncDates = array_values(array_unique($syncDates));
        $reaggregateDates = array_values(array_unique(array_diff($reaggregateDates, $syncDates)));

        if (empty($syncDates) && empty($reaggregateDates)) {
            return response()->json([
                'message' => 'Selected range is already complete and aggregated.',
                'batch_id' => null,
                'total_jobs' => 0,
                'sync_jobs' => 0,
                'reaggregate_jobs' => 0,
            ], 200);
        }

        $batchName = "sync:$startDate:$endDate" . ($force ? ":force" : "");

        // 1. Prevent duplicate active batches for the same range
        $existingBatch = DB::table('job_batches')
            ->where('name', $batchName)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->orderByDesc('created_at')
            ->first();

        if ($existingBatch) {
            return response()->json([
                'message' => "An active sync for this range is already in progress. Re-attaching to existing batch.",
                'batch_id' => $existingBatch->id,
                'total_jobs' => $existingBatch->total_jobs,
                'is_duplicate' => true
            ], 200);
        }

        // 2. Clear out ALL other pending sync batches (auto or manual) to ensure this manual sync runs IMMEDIATELY.
        DB::table('job_batches')
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->update([
                'cancelled_at' => now()->getTimestamp(),
                'finished_at' => now()->getTimestamp()
            ]);

        foreach ($syncDates as $date) {
            $jobs[] = (new SyncForDateJob($date, $force))->onQueue('high');
        }

        foreach (array_chunk($reaggregateDates, 14) as $dateChunk) {
            $jobs[] = (new ReaggregateRangeJob($dateChunk))->onQueue('default');
        }

        $batch = Bus::batch($jobs)
            ->name($batchName)
            ->dispatch();

        return response()->json([
            'message' => $force
                ? "Force sync enqueued for range $startDate to $endDate (" . count($jobs) . " jobs)."
                : "Smart sync enqueued for range $startDate to $endDate ({$batch->totalJobs} jobs: " . count($syncDates) . " remote sync, " . count($reaggregateDates) . " dates to re-aggregate).",
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
            'sync_jobs' => count($syncDates),
            'reaggregate_jobs' => count($reaggregateDates),
        ], 202);
    }

    /**
     * Trigger the data healing process to fix missing names (e.g. Bill Doctor N/A).
     */
    public function healData(Request $request)
    {
        $date = $request->query('date');
        
        $batch = Bus::batch([new HealDataJob($date)])
            ->name("data-healing:" . ($date ?: 'all'))
            ->dispatch();
            
        return response()->json([
            'message' => "Data healing process started in the background.",
            'batch_id' => $batch->id
        ], 202);
    }

    /**
     * Force reset all sync states to unblock the UI.
     */
    public function resetSyncState()
    {
        SyncLog::where('status', 'PENDING')->update(['status' => 'FAILED', 'error_message' => 'Manual Reset']);
        SyncLog::where('status', 'PROCESSING')->update(['status' => 'FAILED', 'error_message' => 'Manual Reset']);
        DB::table('job_batches')->whereNull('finished_at')->update(['cancelled_at' => time(), 'finished_at' => time()]);
        Cache::flush();

        return response()->json(['message' => 'Sync state reset and cache flushed successfully.']);
    }

    /**
     * Repair specific gaps by enqueueing sync jobs for provided dates.
     */
    public function repairGaps(Request $request)
    {
        $rawDates = $request->input('dates', []);
        $force = filter_var($request->input('force', false), FILTER_VALIDATE_BOOL);

        if (!is_array($rawDates)) {
            return response()->json(['error' => 'Dates must be provided as an array'], 422);
        }

        $dates = [];
        $ignored = [];

        foreach ($rawDates as $rawDate) {
            try {
                $date = Carbon::parse($rawDate)->toDateString();
            } catch (\Throwable $e) {
                $ignored[] = [
                    'date' => (string) $rawDate,
                    'reason' => 'Invalid date format',
                ];
                continue;
            }

            if ($this->gapService->shouldSkipDate($date)) {
                $ignored[] = [
                    'date' => $date,
                    'reason' => 'Date is outside the operational gap-detection calendar',
                ];
                continue;
            }

            $dates[$date] = $date;
        }

        $dates = array_values($dates);

        if (empty($dates)) {
            return response()->json([
                'error' => 'No repairable operational dates provided',
                'ignored_dates' => $ignored,
            ], 422);
        }

        $jobs = [];
        foreach ($dates as $date) {
            if ($force) {
                // Remove existing success logs to allow overwrite if forced
                SyncLog::where('sync_date', $date)
                    ->where('sync_type', 'visits')
                    ->delete();
                
                // Also clear cache to be sure
                $this->syncService->clearCacheForDate($date);
            }
            $jobs[] = new SyncForDateJob($date, $force);
        }

        $batch = Bus::batch($jobs)
            ->name("gap-repair:" . count($dates) . "_days")
            ->dispatch();

        return response()->json([
            'message' => 'Repair jobs enqueued for ' . count($dates) . ' dates.',
            'batch_id' => $batch->id,
            'ignored_dates' => $ignored,
        ], 202);
    }

    /**
     * Get status for a queued sync batch.
     */
    public function batchStatus(string $id)
    {
        // Special case for 'auto' - return a global status if any sync is running
        if ($id === 'active' || $id === 'global') {
            $activeSyncs = SyncLog::whereIn('status', ['PROCESSING', 'PENDING'])
                ->where('updated_at', '>', now()->subMinutes(15))
                ->count();
            
            if ($activeSyncs === 0) {
                return response()->json(['finished' => true, 'progress' => 100]);
            }

            // Estimate progress based on recent logs
            return response()->json([
                'id' => 'global',
                'name' => 'Background Sync',
                'progress' => 0, // We can't easily calculate aggregate progress without a batch
                'active_tasks' => $activeSyncs,
                'is_global' => true
            ]);
        }

        $batch = Bus::findBatch($id);

        if (!$batch) {
            return response()->json(['error' => 'Batch not found'], 404);
        }

        $progress = $batch->progress();
        $isWaiting = ($progress == 0 && $batch->pendingJobs > 0);
        $isSilent = (strpos($batch->name, 'auto-sync:') === 0);
        
        if ($isWaiting) {
            $this->syncService->cleanupStaleBatches();
        }

        // Format the name for the message (remove sync: prefix and :force suffix)
        $cleanName = str_replace(['sync:', ':force'], '', $batch->name);

        return response()->json([
            'id' => $batch->id,
            'name' => $batch->name,
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
            'processed_jobs' => $batch->processedJobs(),
            'progress' => $progress,
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
            'is_waiting' => $isWaiting,
            'is_silent' => $isSilent,
            'message' => ($progress < 100)
                ? "Background Syncing... ({$batch->processedJobs()}/{$batch->totalJobs})"
                : null
        ]);
    }

    /**
     * Cancel a specific batch (user-initiated dismiss).
     */
    public function cancelBatch($id)
    {
        $batch = Bus::findBatch($id);
        
        if (!$batch) {
            return response()->json(['error' => 'Batch not found'], 404);
        }

        if (!$batch->finished()) {
            $batch->cancel();
        }

        return response()->json([
            'message' => 'Sync cancelled successfully.',
            'batch_id' => $id,
        ]);
    }


}
