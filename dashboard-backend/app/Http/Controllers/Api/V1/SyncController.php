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
}
