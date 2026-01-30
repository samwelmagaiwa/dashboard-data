<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\DailyDashboardStat;
use App\Models\ClinicStat;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
        set_time_limit(0);
    }

    private function ensureDataIsSynced($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Limit auto-sync window to prevent massive timeouts (e.g. max 90 days)
        if ($start->diffInDays($end) > 90) {
            return;
        }

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            
            // Check if data exists for this day
            $exists = DailyDashboardStat::where('stat_date', $dateString)->exists();
            
            // Sync if missing OR if it's today (to get latest data)
            if (!$exists || $date->isToday()) {
                // Skip if it's future
                if ($date->isFuture()) continue;
                
                $this->syncService->syncForDate($dateString);
            }
        }
    }

    /**
     * Get summary stats for a specific date
     */
    public function getStats(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        // Determine range
        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        // Auto-sync missing dates
        $this->ensureDataIsSynced($startDate, $endDate);

        // Aggregate Base Stats from daily_dashboard_stats table
        $baseStats = DailyDashboardStat::whereDate('stat_date', '>=', $startDate)
            ->whereDate('stat_date', '<=', $endDate)
            ->selectRaw('
                SUM(total_visits) as total_visits,
                SUM(consulted) as consulted,
                SUM(pending) as pending,
                SUM(new_visits) as new_visits,
                SUM(followups) as followups,
                SUM(nhif_visits) as nhif_visits
            ')
            ->first();
        
        // Aggregate Dynamic categories from visits table
        $dynamicStats = \App\Models\Visit::whereDate('visit_date', '>=', $startDate)
            ->whereDate('visit_date', '<=', $endDate)
            ->selectRaw('
                SUM(CASE WHEN pat_catg_nm LIKE "%FOREIGNER%" THEN 1 ELSE 0 END) as foreigner,
                SUM(CASE WHEN pat_catg_nm LIKE "%PUBLIC%" THEN 1 ELSE 0 END) as public,
                SUM(CASE WHEN pat_catg_nm LIKE "%NHIF%" THEN 1 ELSE 0 END) as nhif,
                SUM(CASE WHEN pat_catg_nm LIKE "%IPPM%PRIVATE%" THEN 1 ELSE 0 END) as ippm_private,
                SUM(CASE WHEN pat_catg_nm LIKE "%IPPM%CREDIT%" THEN 1 ELSE 0 END) as ippm_credit,
                SUM(CASE WHEN pat_catg_nm LIKE "%COST%SHARING%" THEN 1 ELSE 0 END) as cost_sharing,
                SUM(CASE WHEN pat_catg_nm LIKE "%WAIVER%" THEN 1 ELSE 0 END) as waivers,
                SUM(CASE WHEN pat_catg_nm LIKE "%NSSF%" THEN 1 ELSE 0 END) as nssf
            ')
            ->first();

        $response = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_visits' => (int)($baseStats->total_visits ?? 0),
            'consulted' => (int)($baseStats->consulted ?? 0),
            'pending' => (int)($baseStats->pending ?? 0),
            'new_visits' => (int)($baseStats->new_visits ?? 0),
            'followups' => (int)($baseStats->followups ?? 0),
            'nhif_visits' => (int)($baseStats->nhif_visits ?? 0),
            'categories' => [
                'foreigner' => (int)($dynamicStats->foreigner ?? 0),
                'public' => (int)($dynamicStats->public ?? 0),
                'nhif' => (int)($dynamicStats->nhif ?? $baseStats->nhif_visits ?? 0),
                'ippm_private' => (int)($dynamicStats->ippm_private ?? 0),
                'ippm_credit' => (int)($dynamicStats->ippm_credit ?? 0),
                'cost_sharing' => (int)($dynamicStats->cost_sharing ?? 0),
                'waivers' => (int)($dynamicStats->waivers ?? 0),
                'nssf' => (int)($dynamicStats->nssf ?? 0),
            ]
        ];

        return response()->json($response);
    }

    /**
     * Get clinic-wise breakdown for a specific date
     */
    public function getClinicBreakdown(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        // Determine range
        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        // Auto-sync missing dates
        $this->ensureDataIsSynced($startDate, $endDate);
        
        $breakdown = ClinicStat::whereBetween('stat_date', [$startDate, $endDate])
            ->selectRaw('
                clinic_name,
                SUM(total_visits) as total_visits
            ')
            ->groupBy('clinic_name')
            ->orderBy('total_visits', 'desc')
            ->get();
            
        return response()->json($breakdown);
    }
}
