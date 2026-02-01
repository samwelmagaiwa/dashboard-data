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

        $syncedDays = 0;
        $errors = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            try {
                $result = $this->syncService->syncForDate($dateString);
                if ($result['success']) {
                    $syncedDays++;
                } else {
                    $errors[$dateString] = $result['error'];
                }
            } catch (\Exception $e) {
                $errors[$dateString] = $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Sync completed for range $startDate to $endDate",
            'synced_days' => $syncedDays,
            'errors' => $errors
        ]);
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

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Start date and end date are required'], 400);
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Safety limit: prevent enqueueing more than 366 days at once.
        if ($start->diffInDays($end) > 366) {
            return response()->json(['error' => 'Range too large. Please enqueue max 1 year at a time.'], 400);
        }

        $jobs = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $jobs[] = new \App\Jobs\SyncForDateJob($date->toDateString());
        }

        $batch = Bus::batch($jobs)
            ->name("sync:$startDate:$endDate")
            ->dispatch();

        return response()->json([
            'message' => "Sync enqueued for range $startDate to $endDate",
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
        ], 202);
    }

    /**
     * Get status for a queued sync batch.
     */
    public function batchStatus(string $id)
    {
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
