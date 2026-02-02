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
        
        // Only auto-sync today's data to keep requests fast
        // Historical data should already be synced or can be synced via manual sync endpoint
        $today = Carbon::today();
        
        // Only sync if the range includes today
        if ($start->lte($today) && $end->gte($today)) {
            // Check if today's data already exists
            $exists = DailyDashboardStat::where('stat_date', $today->toDateString())->exists();
            
            if (!$exists) {
                $this->syncService->syncForDate($today->toDateString());
            }
        }
        
        // For historical data: return immediately without syncing
        // This makes all date range queries fast regardless of range size
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


        // Aggregate ALL stats from daily_dashboard_stats (fast, volume-based)
        $baseStats = DailyDashboardStat::whereDate('stat_date', '>=', $startDate)
            ->whereDate('stat_date', '<=', $endDate)
            ->selectRaw('
                SUM(total_visits) as total_visits,
                SUM(consulted) as consulted,
                SUM(pending) as pending,
                SUM(new_visits) as new_visits,
                SUM(followups) as followups,
                SUM(nhif_visits) as nhif_visits,
                SUM(foreigner) as foreigner,
                SUM(public) as public,
                SUM(ippm_private) as ippm_private,
                SUM(ippm_credit) as ippm_credit,
                SUM(cost_sharing) as cost_sharing,
                SUM(waivers) as waivers,
                SUM(nssf) as nssf,
                SUM(emergency) as emergency_visits
            ')
            ->first();

        $expectedDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $aggregatedDays = DailyDashboardStat::whereDate('stat_date', '>=', $startDate)
            ->whereDate('stat_date', '<=', $endDate)
            ->count();

        $response = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'meta' => [
                'expected_days' => $expectedDays,
                'aggregated_days' => $aggregatedDays,
                'is_fully_aggregated' => $aggregatedDays >= $expectedDays,
            ],
            'total_visits' => (int)($baseStats->total_visits ?? 0),
            'total_patients' => (int)($baseStats->total_visits ?? 0), // Count all appearances
            'consulted' => (int)($baseStats->consulted ?? 0),
            'pending' => (int)($baseStats->pending ?? 0),
            'new_visits' => (int)($baseStats->new_visits ?? 0),
            'followups' => (int)($baseStats->followups ?? 0),
            'nhif_visits' => (int)($baseStats->nhif_visits ?? 0),
            'emergency' => (int)($baseStats->emergency_visits ?? 0),
            'emergency_patients' => (int)($baseStats->emergency_visits ?? 0), // Count all appearances
            'categories' => [
                'foreigner' => (int)($baseStats->foreigner ?? 0),
                'public' => (int)($baseStats->public ?? 0),
                'nhif' => (int)($baseStats->nhif_visits ?? 0),
                'ippm_private' => (int)($baseStats->ippm_private ?? 0),
                'ippm_credit' => (int)($baseStats->ippm_credit ?? 0),
                'cost_sharing' => (int)($baseStats->cost_sharing ?? 0),
                'waivers' => (int)($baseStats->waivers ?? 0),
                'nssf' => (int)($baseStats->nssf ?? 0),
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

        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }


        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = $start->diffInDays($end) + 1;

        // Previous period with the same duration.
        $prevStart = $start->copy()->subDays($days);
        $prevEnd = $end->copy()->subDays($days);

        // Current period (top clinics only).
        $current = ClinicStat::whereDate('stat_date', '>=', $startDate)
            ->whereDate('stat_date', '<=', $endDate)
            ->selectRaw('clinic_name, SUM(total_visits) as total_visits')
            ->groupBy('clinic_name')
            ->orderByDesc('total_visits')
            ->limit(50)
            ->get();

        $prevCounts = ClinicStat::whereDate('stat_date', '>=', $prevStart->toDateString())
            ->whereDate('stat_date', '<=', $prevEnd->toDateString())
            ->selectRaw('clinic_name, SUM(total_visits) as total_visits')
            ->groupBy('clinic_name')
            ->pluck('total_visits', 'clinic_name')
            ->toArray();

        $compLabel = 'vs ' . $prevStart->format('M d');
        if ($prevStart->diffInDays($prevEnd) > 0) {
            $compLabel .= ' - ' . $prevEnd->format('M d');
        }

        $breakdown = [];
        foreach ($current as $row) {
            $clinicName = $row->clinic_name;
            $cur = (int) ($row->total_visits ?? 0);
            $prev = (int) ($prevCounts[$clinicName] ?? 0);

            $trend = 0.0;
            if ($prev > 0) {
                $trend = round((($cur - $prev) / $prev) * 100, 1);
            } elseif ($cur > 0) {
                $trend = 100.0;
            }

            $interpretation = 'Stable';
            if ($cur === 0) {
                $interpretation = 'No Visits';
            } elseif ($trend > 0) {
                $interpretation = 'Increasing';
            } elseif ($trend < 0) {
                $interpretation = 'Decreasing';
            }

            $breakdown[] = [
                'clinic_name' => $clinicName,
                'total_visits' => $cur,
                'trend' => $trend,
                'interpretation' => $interpretation,
                'comparison_dates' => $compLabel,
            ];
        }

        return response()->json($breakdown);
    }
}
