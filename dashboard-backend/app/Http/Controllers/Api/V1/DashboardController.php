<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyDashboardStat;
use App\Models\ClinicStat;
use App\Services\SyncService;
use App\Services\GapDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $syncService;
    protected $gapService;

    public function __construct(SyncService $syncService, GapDetectionService $gapService)
    {
        $this->syncService = $syncService;
        $this->gapService = $gapService;
    }

    /**
     * Get summary stats for a specific date or range.
     * Uses Cache to optimize read speed.
     */
    public function getStats(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        $cacheKey = "dashboard_stats_{$startDate}_{$endDate}";
        $isToday = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
        $ttl = $isToday ? 60 : 600; // 1 minute for today, 10 minutes for historical
        
        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            $baseStats = DailyDashboardStat::whereDate('stat_date', '>=', $startDate)
                ->whereDate('stat_date', '<=', $endDate)
                ->selectRaw('
                    SUM(total_visits) as total_visits,
                    SUM(consulted) as consulted,
                    (SUM(total_visits) - SUM(consulted)) as pending,
                    SUM(new_visits) as new_visits,
                    SUM(followups) as followups,
                    SUM(nhif_visits) as nhif_visits,
                    SUM(foreigner) as foreigner,
                    SUM(public) as public,
                    SUM(ippm_private) as ippm_private,
                    SUM(ippm_credit) as ippm_credit,
                    SUM(cost_sharing) as cost_sharing,
                    SUM(nssf) as nssf,
                    SUM(emergency) as emergency_visits
                ')
                ->first();

            $expectedDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            $aggregatedDays = DailyDashboardStat::whereDate('stat_date', '>=', $startDate)
                ->whereDate('stat_date', '<=', $endDate)
                ->count();

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'meta' => [
                    'expected_days' => $expectedDays,
                    'aggregated_days' => $aggregatedDays,
                    'is_fully_aggregated' => $aggregatedDays >= $expectedDays,
                    'cached_at' => now()->toDateTimeString(),
                ],
                'total_visits' => (int)($baseStats->total_visits ?? 0),
                'total_patients' => (int)($baseStats->total_visits ?? 0),
                'consulted' => (int)($baseStats->consulted ?? 0),
                'pending' => (int)($baseStats->pending ?? 0),
                'new_visits' => (int)($baseStats->new_visits ?? 0),
                'followups' => (int)($baseStats->followups ?? 0),
                'nhif_visits' => (int)($baseStats->nhif_visits ?? 0),
                'emergency' => (int)($baseStats->emergency_visits ?? 0),
                'emergency_patients' => (int)($baseStats->emergency_visits ?? 0),
                'categories' => [
                    'foreigner' => (int)($baseStats->foreigner ?? 0),
                    'public' => (int)($baseStats->public ?? 0),
                    'nhif' => (int)($baseStats->nhif_visits ?? 0),
                    'ippm_private' => (int)($baseStats->ippm_private ?? 0),
                    'ippm_credit' => (int)($baseStats->ippm_credit ?? 0),
                    'cost_sharing' => (int)($baseStats->cost_sharing ?? 0),
                    'nssf' => (int)($baseStats->nssf ?? 0),
                ]
            ];
        });
    }

    /**
     * Get clinic-wise breakdown for a specific date range.
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

        $cacheKey = "clinic_breakdown_{$startDate}_{$endDate}";
        $isToday = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $days = $start->diffInDays($end) + 1;

            $prevStart = $start->copy()->subDays($days);
            $prevEnd = $end->copy()->subDays($days);

            $current = ClinicStat::whereDate('stat_date', '>=', $startDate)
                ->whereDate('stat_date', '<=', $endDate)
                ->selectRaw('clinic_name, SUM(total_visits) as total_visits')
                ->groupBy('clinic_name')
                ->orderByDesc('total_visits')
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
                    'previous_visits' => $prev,
                    'trend' => $trend,
                    'interpretation' => $interpretation,
                    'comparison_dates' => $compLabel,
                ];
            }

            return $breakdown;
        });
    }

    /**
     * Get missing data dates (Gaps).
     */
    public function getGaps(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());

        $gaps = $this->gapService->detectGaps($startDate, $endDate);

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'gaps' => $gaps,
            'gap_count' => count($gaps)
        ]);
    }

    /**
     * Get data for pie charts (Gender, Visit Type, Referral Hospital).
     */
    public function getPieStats(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        $cacheKey = "pie_stats_{$startDate}_{$endDate}";
        $isToday = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            // 1. Gender Distribution
            // Since API doesn't return 'patSex' yet, this will mostly be 0/null for now.
            // We count 'M' and 'F' specifically.
            $genderStats = \App\Models\Visit::whereDate('visit_date', '>=', $startDate)
                ->whereDate('visit_date', '<=', $endDate)
                ->selectRaw('
                    SUM(CASE WHEN gender = "M" THEN 1 ELSE 0 END) as male,
                    SUM(CASE WHEN gender = "F" THEN 1 ELSE 0 END) as female
                ')
                ->first();

            // 2. Visit Type (New vs Follow-Up)
            // N = New, F = Follow-up
            $visitTypeStats = \App\Models\Visit::whereDate('visit_date', '>=', $startDate)
                ->whereDate('visit_date', '<=', $endDate)
                ->selectRaw('
                    SUM(CASE WHEN visit_type = "N" THEN 1 ELSE 0 END) as new_visits,
                    SUM(CASE WHEN visit_type = "F" THEN 1 ELSE 0 END) as followups
                ')
                ->first();

            // 3. Referral Hospital Distribution
            // Group by ref_hosp, count, take top 5. Group rest as "Others".
            $refStats = \App\Models\Visit::whereDate('visit_date', '>=', $startDate)
                ->whereDate('visit_date', '<=', $endDate)
                ->whereNotNull('ref_hosp')
                ->where('ref_hosp', '!=', '')
                ->select('ref_hosp', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                ->groupBy('ref_hosp')
                ->orderByDesc('total')
                ->get();

            $topRef = $refStats->take(5);
            $othersCount = $refStats->slice(5)->sum('total');
            
            $referralData = $topRef->map(function($item) {
                return [
                    'name' => $item->ref_hosp,
                    'count' => $item->total
                ];
            })->values();

            if ($othersCount > 0) {
                $referralData->push([
                    'name' => 'Others',
                    'count' => $othersCount
                ]);
            }

            return [
                'gender' => [
                    'male' => (int)($genderStats->male ?? 0),
                    'female' => (int)($genderStats->female ?? 0),
                ],
                'visit_type' => [
                    'new' => (int)($visitTypeStats->new_visits ?? 0),
                    'followup' => (int)($visitTypeStats->followups ?? 0),
                ],
                'referral' => $referralData
            ];
        });
    }

    /**
     * Get comparison stats for Radar Chart (Current vs Previous Period)
     */
    public function getComparisonStats(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        $cacheKey = "comp_stats_{$startDate}_{$endDate}";
        $isToday = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $days = $start->diffInDays($end) + 1;

            $prevStart = $start->copy()->subDays($days);
            $prevEnd = $end->copy()->subDays($days);

            // Helper to get category counts
            $getCatCounts = function($s, $e) {
                return DailyDashboardStat::whereDate('stat_date', '>=', $s)
                    ->whereDate('stat_date', '<=', $e)
                    ->selectRaw('
                        SUM(public) as public,
                        SUM(nhif_visits) as nhif,
                        SUM(ippm_private) as ippm_private,
                        SUM(ippm_credit) as ippm_credit,
                        SUM(cost_sharing) as cost_sharing,
                        SUM(nssf) as nssf,
                        SUM(foreigner) as foreigner
                    ')
                    ->first();
            };

            $current = $getCatCounts($startDate, $endDate);
            $previous = $getCatCounts($prevStart->toDateString(), $prevEnd->toDateString());

            // Normalize labels for radar chart
            $labels = ['PUBLIC', 'NHIF', 'IPPM-PRV', 'IPPM-CRD', 'COST-SH', 'NSSF', 'FOREIGN'];
            
            return [
                'labels' => $labels,
                'period_labels' => [
                    'current' => $start->format('M d') . ' - ' . $end->format('M d'),
                    'previous' => $prevStart->format('M d') . ' - ' . $prevEnd->format('M d'),
                ],
                'current' => [
                    (int)($current->public ?? 0),
                    (int)($current->nhif ?? 0),
                    (int)($current->ippm_private ?? 0),
                    (int)($current->ippm_credit ?? 0),
                    (int)($current->cost_sharing ?? 0),
                    (int)($current->nssf ?? 0),
                    (int)($current->foreigner ?? 0),
                ],
                'previous' => [
                    (int)($previous->public ?? 0),
                    (int)($previous->nhif ?? 0),
                    (int)($previous->ippm_private ?? 0),
                    (int)($previous->ippm_credit ?? 0),
                    (int)($previous->cost_sharing ?? 0),
                    (int)($previous->nssf ?? 0),
                    (int)($previous->foreigner ?? 0),
                ]
            ];
        });
    }

    /**
     * Get aggregate service trends for the grouped bar chart.
     */
    public function getServiceTrends(Request $request)
    {
        $period = $request->query('period', 'day');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!$startDate || !$endDate) {
            $startDate = date('Y-m-d');
            $endDate = $startDate;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Expand range based on period to show "context" trends
        switch ($period) {
            case 'day':
                // Show full week (Mon-Sun) containing the startDate
                $startDate = $start->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $endDate = $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
                break;
            case 'week':
                // Show full month
                $startDate = $start->copy()->startOfMonth()->toDateString();
                $endDate = $start->copy()->endOfMonth()->toDateString();
                break;
            case 'month':
                // Show full year
                $startDate = $start->copy()->startOfYear()->toDateString();
                $endDate = $start->copy()->endOfYear()->toDateString();
                break;
            case 'range':
                // Use the provided range as is
                break;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $cacheKey = "service_trends_{$period}_{$startDate}_{$endDate}_v5";
        $isToday = ($endDate === date('Y-m-d'));
        $ttl = $isToday ? 60 : 3600;

        return Cache::remember($cacheKey, $ttl, function() use ($period, $startDate, $endDate, $start, $end) {
            $labels = [];
            $dataMap = [];

            // 1. Generate empty containers for the full range
            switch ($period) {
                case 'day':
                    for ($i = 0; $i < 7; $i++) {
                        $d = $start->copy()->addDays($i);
                        $label = $d->format('l');
                        $labels[] = $label;
                        $dataMap[$d->toDateString()] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                    }
                    break;
                case 'range':
                    $tempStart = $start->copy();
                    while ($tempStart <= $end) {
                        $label = $tempStart->format('d M');
                        $labels[] = $label;
                        $dataMap[$tempStart->toDateString()] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                        $tempStart->addDay();
                        if (count($labels) > 31) break;
                    }
                    break;
                case 'week':
                    $tempStart = $start->copy();
                    while ($tempStart <= $end) {
                        $label = "Week " . $tempStart->weekOfMonth . " (" . $tempStart->format('M') . ")";
                        $labels[] = $label;
                        $key = $tempStart->format('Y-W'); // Year and Week number
                        $dataMap[$key] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                        $tempStart->addWeek();
                    }
                    break;
                case 'month':
                    for ($i = 1; $i <= 12; $i++) {
                        $d = Carbon::create($start->year, $i, 1);
                        $label = $d->format('M');
                        $labels[] = $label;
                        $dataMap[$d->format('Y-m')] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                    }
                    break;
                case 'year':
                    for ($i = -4; $i <= 0; $i++) {
                        $d = $start->copy()->addYears($i);
                        $label = $d->year;
                        $labels[] = $label;
                        $dataMap[$label] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                    }
                    break;
            }

            // 2. Fetch data
            $query = DailyDashboardStat::whereDate('stat_date', '>=', $startDate)
                ->whereDate('stat_date', '<=', $endDate);

            switch ($period) {
                case 'day':
                case 'range':
                    $query->selectRaw('DATE(stat_date) as group_key');
                    break;
                case 'week':
                    $query->selectRaw('DATE_FORMAT(stat_date, "%Y-%u") as group_key'); // Year-WeekNumber
                    break;
                case 'month':
                    $query->selectRaw('DATE_FORMAT(stat_date, "%Y-%m") as group_key');
                    break;
                case 'year':
                    $query->selectRaw('YEAR(stat_date) as group_key');
                    break;
            }

            $results = $query->selectRaw('
                SUM(total_visits) as opd,
                SUM(emergency) as emergency,
                SUM(consulted) as consulted,
                (SUM(total_visits) - SUM(consulted)) as not_consulted,
                SUM(new_visits) as new_visits,
                SUM(followups) as followups
            ')
            ->groupBy('group_key')
            ->get();

            // 3. Map results into the pre-filled dataMap
            foreach ($results as $row) {
                $gk = (string)$row->group_key;
                if (isset($dataMap[$gk])) {
                    $dataMap[$gk]['opd'] = (int)$row->opd;
                    $dataMap[$gk]['emergency'] = (int)$row->emergency;
                    $dataMap[$gk]['consulted'] = (int)$row->consulted;
                    $dataMap[$gk]['not_consulted'] = (int)$row->not_consulted;
                    $dataMap[$gk]['new_visits'] = (int)$row->new_visits;
                    $dataMap[$gk]['followups'] = (int)$row->followups;
                }
            }

            // 4. Transform into Chart.js format
            $orderedData = collect(array_values($dataMap));

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Total OPD',
                        'data' => $orderedData->pluck('opd'),
                    ],
                    [
                        'label' => 'Emergency',
                        'data' => $orderedData->pluck('emergency'),
                    ],
                    [
                        'label' => 'Consulted',
                        'data' => $orderedData->pluck('consulted'),
                    ],
                    [
                        'label' => 'Not Consulted',
                        'data' => $orderedData->pluck('not_consulted'),
                    ],
                    [
                        'label' => 'New Visits',
                        'data' => $orderedData->pluck('new_visits'),
                    ],
                    [
                        'label' => 'Follow-ups',
                        'data' => $orderedData->pluck('followups'),
                    ]
                ]
            ];
        });
    }

    /**
     * Lightweight check to see if data has changed.
     */
    public function checkUpdates(Request $request)
    {
        $today = date('Y-m-d');
        
        // Get the latest timestamp from either daily stats or clinic stats
        $latestStat = DailyDashboardStat::select('updated_at')->orderByDesc('updated_at')->first();
        $latestClinic = ClinicStat::select('updated_at')->orderByDesc('updated_at')->first();
        
        $version = md5(
            ($latestStat ? $latestStat->updated_at->toDateTimeString() : 'none') . 
            ($latestClinic ? $latestClinic->updated_at->toDateTimeString() : 'none')
        );

        return response()->json([
            'version' => $version,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
