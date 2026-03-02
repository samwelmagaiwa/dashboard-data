<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\SyncLog;
use App\Models\DailyDashboardStat;
use App\Models\ClinicStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SyncController extends Controller
{
    protected $syncService;

    public function __construct(\App\Services\SyncService $syncService)
    {
        $this->syncService = $syncService;
        set_time_limit(0);
    }

    public function sync($date = null)
    {
        // Handle Ymd or Y-m-d
        if ($date && strlen($date) === 8 && is_numeric($date)) {
            $formattedDate = \Carbon\Carbon::createFromFormat('Ymd', $date)->toDateString();
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
            $formattedDate = \Carbon\Carbon::createFromFormat('Ymd', $date)->toDateString();
        } else {
            $formattedDate = $date ?: date('Y-m-d');
        }

        \App\Jobs\SyncForDateJob::dispatch($formattedDate);

        return response()->json([
            'message' => "Sync job dispatched for date {$formattedDate}",
            'status' => 'queued'
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

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        $jobs = [];
        foreach ($dates as $date) {
            $jobs[] = new \App\Jobs\SyncForDateJob($date, $force);
        }

        $batch = Bus::batch($jobs)
            ->name("sync:$startDate:$endDate" . ($force ? ":force" : ""))
            ->dispatch();

        return response()->json([
            'message' => "Sync enqueued for range $startDate to $endDate (" . count($dates) . " jobs)" . ($force ? " [FORCE]" : ""),
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
        ], 202);
    }

    /**
     * Trigger the data healing process to fix missing names (e.g. Bill Doctor N/A).
     */
    public function healData(Request $request)
    {
        $date = $request->query('date');
        
        $batch = Bus::batch([new \App\Jobs\HealDataJob($date)])
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
        \App\Models\SyncLog::where('status', 'PENDING')->update(['status' => 'FAILED', 'error_message' => 'Manual Reset']);
        \App\Models\SyncLog::where('status', 'PROCESSING')->update(['status' => 'FAILED', 'error_message' => 'Manual Reset']);
        DB::table('job_batches')->whereNull('finished_at')->update(['cancelled_at' => time(), 'finished_at' => time()]);
        Cache::flush();

        return response()->json(['message' => 'Sync state reset and cache flushed successfully.']);
    }

    /**
     * Repair specific gaps by enqueueing sync jobs for provided dates.
     */
    public function repairGaps(Request $request)
    {
        $dates = $request->input('dates', []);
        $force = $request->input('force', false);
        
        if (empty($dates)) {
            return response()->json(['error' => 'No dates provided'], 400);
        }

        $jobs = [];
        foreach ($dates as $date) {
            if ($force) {
                // Remove existing success logs to allow overwrite if forced
                \App\Models\SyncLog::where('sync_date', $date)
                    ->where('sync_type', 'visits')
                    ->delete();
                
                // Also clear cache to be sure
                $this->syncService->clearCacheForDate($date);
            }
            $jobs[] = new \App\Jobs\SyncForDateJob($date, $force);
        }

        $batch = Bus::batch($jobs)
            ->name("gap-repair:" . count($dates) . "_days")
            ->dispatch();

        return response()->json([
            'message' => 'Repair jobs enqueued for ' . count($dates) . ' dates.',
            'batch_id' => $batch->id,
        ], 202);
    }

    /**
     * Get status for a queued sync batch.
     */
    public function batchStatus(string $id)
    {
        // Special case for 'auto' - return a global status if any sync is running
        if ($id === 'active' || $id === 'global') {
            $activeSyncs = \App\Models\SyncLog::whereIn('status', ['PROCESSING', 'PENDING'])
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

        return response()->json([
            'id' => $batch->id,
            'name' => $batch->name,
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
            'processed_jobs' => $batch->processedJobs(),
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
        ]);
    }
}
