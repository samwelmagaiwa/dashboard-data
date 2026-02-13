<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyDashboardStat;
use App\Models\ClinicStat;
use App\Models\Visit;
use App\Models\DailyReferralStat;
use App\Services\SyncService;
use App\Services\GapDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Cache version - increment this when cache structure changes.
     * This invalidates all dashboard caches without manual key updates.
     */
    private const CACHE_VERSION = 4;

    protected $syncService;
    protected $gapService;

    public function __construct(SyncService $syncService, GapDetectionService $gapService)
    {
        $this->syncService = $syncService;
        $this->gapService = $gapService;
    }

    /**
     * Generate a versioned cache key.
     */
    private function cacheKey(string $prefix, string ...$parts): string
    {
        return $prefix . '_' . implode('_', $parts) . '_v' . self::CACHE_VERSION;
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

        \Illuminate\Support\Facades\Log::info("[Dashboard] Requesting stats for range: $startDate to $endDate");

        $cacheKey = $this->cacheKey('dashboard_stats', $startDate, $endDate);
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
                'emergency_visits' => (int)($baseStats->emergency_visits ?? 0),
                'foreigner' => (int)($baseStats->foreigner ?? 0),
                'public' => (int)($baseStats->public ?? 0),
                'ippm_private' => (int)($baseStats->ippm_private ?? 0),
                'ippm_credit' => (int)($baseStats->ippm_credit ?? 0),
                'cost_sharing' => (int)($baseStats->cost_sharing ?? 0),
                'nssf' => (int)($baseStats->nssf ?? 0),
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

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $period = $request->query('period', 'day');

        // --- STRICT PERIOD LOGIC (Fix: Obedience to selection) ---
        // 1. If 'day' is selected, we STRICTLY show that day vs previous day (No expansion)

        // 2. If 'year' is selected, ensure we show full Jan-Dec (standard annual overview)
        if ($period === 'year') {
            $start = $start->copy()->startOfYear();
            $end = $end->copy()->endOfYear();
        }

        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $cacheKey = $this->cacheKey('clinic_breakdown_v4', $startDate, $endDate);
        $isToday = ($startDate <= date('Y-m-d') && date('Y-m-d') <= $endDate);
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate, $start, $end) {
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
                    $trend = (($cur - $prev) / $prev) * 100;
                    // Cap at 100% as requested by user
                    $trend = max(-100.0, min(100.0, round($trend, 1)));
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
     * Get data for pie charts (Gender, Visit Type).
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

        $cacheKey = $this->cacheKey('pie_stats', $startDate, $endDate);
        $isToday = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            // 1. Gender Distribution (Still scanning visits as it's not in stats yet)
            $genderStats = \App\Models\Visit::whereDate('visit_date', '>=', $startDate)
                ->whereDate('visit_date', '<=', $endDate)
                ->selectRaw('
                    SUM(CASE WHEN gender = "M" THEN 1 ELSE 0 END) as male,
                    SUM(CASE WHEN gender = "F" THEN 1 ELSE 0 END) as female
                ')
                ->first();

            // 2. Visit Type (Use aggregated daily_dashboard_stats)
            $visitTypeStats = \App\Models\DailyDashboardStat::whereDate('stat_date', '>=', $startDate)
                ->whereDate('stat_date', '<=', $endDate)
                ->selectRaw('SUM(new_visits) as new_visits, SUM(followups) as followups')
                ->first();

            // 3. Referral Hospital Distribution (Use new DailyReferralStat table)
            $refStats = \App\Models\DailyReferralStat::whereDate('stat_date', '>=', $startDate)
                ->whereDate('stat_date', '<=', $endDate)
                ->select('ref_hosp_code as code', 'ref_hosp_name as name', DB::raw('SUM(count) as total'))
                ->groupBy('ref_hosp_code', 'ref_hosp_name')
                ->orderByDesc('total')
                ->get();

            // Format for pie chart (top 5 + Others)
            $topRef = $refStats->take(5);
            $othersCount = $refStats->slice(5)->sum('total');
            
            $referralData = $topRef->map(function($item) {
                return [
                    'name' => $item->name ?: $item->code,
                    'count' => (int)$item->total
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

        $cacheKey = $this->cacheKey('comp_stats', $startDate, $endDate);
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
     * Now dynamically respects the exact date range to match cards data.
     */
    public function getServiceTrends(Request $request)
    {
        $period = $request->query('period', 'day');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $breakdown = $request->query('breakdown', null); // 'monthly' for monthly breakdown

        if (!$startDate || !$endDate) {
            $startDate = date('Y-m-d');
            $endDate = $startDate;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // --- EXPANSION LOGIC FOR CHART CONTEXT ---
        // 1. If 'day' is selected (single day range), expand to full week (Mon-Sun)
        if ($period === 'day' && $start->diffInDays($end) == 0 && !$breakdown) {
            $start = $start->copy()->startOfWeek(Carbon::MONDAY);
            $end = $end->copy()->endOfWeek(Carbon::SUNDAY);
        }

        // 2. If 'year' is selected, ensure we show full Jan-Dec
        if ($period === 'year' && !$breakdown) {
            $start = $start->copy()->startOfYear();
            $end = $end->copy()->endOfYear();
        }
        // -----------------------------------------

        // Use the exact range provided - no expansion
        // This ensures bars match cards data exactly

        $cacheKey = $this->cacheKey('service_trends', $period, $startDate, $endDate, $breakdown ?? 'default');
        $now = date('Y-m-d');
        // Reduced cache TTL for more responsive data updates
        $includesToday = ($startDate <= $now && $endDate >= $now);
        $ttl = $includesToday ? 30 : 300; // 30 seconds for today, 5 minutes otherwise

        return Cache::remember($cacheKey, $ttl, function() use ($period, $startDate, $endDate, $start, $end, $breakdown) {
            $labels = [];
            $dataMap = [];
            $days = $start->diffInDays($end) + 1;

            // If breakdown=monthly is enabled, override period handling
            $effectivePeriod = $breakdown === 'monthly' ? 'monthly_breakdown' : $period;

            // 1. Generate empty containers dynamically based on exact range
            switch ($effectivePeriod) {
                case 'monthly_breakdown':
                    // Monthly breakdown: show data grouped by month
                    $tempStart = $start->copy()->startOfMonth();
                    while ($tempStart <= $end) {
                        $label = $tempStart->format('M Y');
                        $key = $tempStart->format('Y-m');
                        $labels[] = $label;
                        $dataMap[$key] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                        $tempStart->addMonth();
                    }
                    break;
                case 'day':
                    // Logic for displaying days (now potentially a full week)
                    $tempStart = $start->copy();
                    while ($tempStart <= $end) {
                        // Format: "Monday - 12"
                        $label = $tempStart->format('l - d');
                        $labels[] = $label;
                        $dataMap[$tempStart->toDateString()] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                        $tempStart->addDay();
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
                    // Iterate through actual weeks in range
                    $tempStart = $start->copy()->startOfWeek(Carbon::MONDAY);
                    $tempEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);
                    $seenWeeks = [];
                    while ($tempStart <= $tempEnd) {
                        $key = $tempStart->format('Y-W');
                        if (!isset($seenWeeks[$key])) {
                            $label = "Week " . $tempStart->weekOfMonth . " (" . $tempStart->format('M') . ")";
                            $labels[] = $label;
                            $dataMap[$key] = [
                                'label' => $label,
                                'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                                'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                            ];
                            $seenWeeks[$key] = true;
                        }
                        $tempStart->addWeek();
                    }
                    break;
                case 'month':
                    // Month View: Show Weekly Breakdown (Week 1, Week 2, etc)
                    // We iterate through weeks within the selected month
                    $tempStart = $start->copy()->startOfMonth();
                    $tempEnd = $start->copy()->endOfMonth();
                    
                    // Grouping by Sunday-based weeks or just standard 7-day windows?
                    // Let's use standard Carbon week blocks starting from the 1st
                    $weekNum = 1;
                    while ($tempStart <= $tempEnd) {
                        $label = "Week $weekNum";
                        $key = "W$weekNum";
                        $labels[] = $label;
                        $dataMap[$key] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0, 
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0,
                            'start' => $tempStart->toDateString(),
                            'end' => $tempStart->copy()->addDays(6)->min($tempEnd)->toDateString()
                        ];
                        $tempStart->addDays(7);
                        $weekNum++;
                    }
                    break;
                case 'year':
                    if ($breakdown === 'monthly') {
                         // Yearly Breakdown (12 Months)
                        $tempStart = $start->copy()->startOfMonth();
                        while ($tempStart <= $end) {
                            $label = $tempStart->format('M'); // Jan, Feb
                            $key = $tempStart->format('Y-m');
                            if (!isset($dataMap[$key])) {
                                $labels[] = $label;
                                $dataMap[$key] = [
                                    'label' => $label,
                                    'opd' => 0, 'emergency' => 0, 'consulted' => 0,
                                    'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                                ];
                            }
                            $tempStart->addMonth();
                        }
                    } else {
                        // Default Year View (Single Aggregated Bar)
                        $label = $start->format('Y');
                        $key = $start->format('Y');
                        $labels[] = $label;
                         $dataMap[$key] = [
                            'label' => $label,
                            'opd' => 0, 'emergency' => 0, 'consulted' => 0,
                            'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                        ];
                    }
                    break;
            }

            // 2. Fetch data
            $query = DailyDashboardStat::whereDate('stat_date', '>=', $start->toDateString())
                ->whereDate('stat_date', '<=', $end->toDateString());

            switch ($effectivePeriod) {
                case 'monthly_breakdown':
                    $query->selectRaw('DATE_FORMAT(stat_date, "%Y-%m") as group_key');
                    break;
                case 'day':
                case 'range':
                    $query->selectRaw('DATE(stat_date) as group_key');
                    break;
                case 'year':
                    $query->selectRaw('DATE_FORMAT(stat_date, "%Y") as group_key');
                    break;
                case 'month':
                    $query->selectRaw('DATE(stat_date) as group_key');
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
                
                // For the 'month' case (which is now weekly), we need to find which week container the date belongs to
                if ($period === 'month' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $gk)) {
                    foreach ($dataMap as $key => $container) {
                        if (isset($container['start']) && isset($container['end'])) {
                            if ($gk >= $container['start'] && $gk <= $container['end']) {
                                $dataMap[$key]['opd'] += (int)$row->opd;
                                $dataMap[$key]['emergency'] += (int)$row->emergency;
                                $dataMap[$key]['consulted'] += (int)$row->consulted;
                                $dataMap[$key]['not_consulted'] += (int)$row->not_consulted;
                                $dataMap[$key]['new_visits'] += (int)$row->new_visits;
                                $dataMap[$key]['followups'] += (int)$row->followups;
                                break;
                            }
                        }
                    }
                    continue;
                }

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
                        'type' => 'line',
                        'label' => 'Total Trend',
                        'data' => $orderedData->pluck('opd'),
                        'borderColor' => '#1e293b', // Dark slate for high contrast
                        'borderWidth' => 2,
                        'pointBackgroundColor' => '#fff',
                        'pointBorderColor' => '#1e293b',
                        'pointRadius' => 4,
                        'pointHoverRadius' => 6,
                        'fill' => false,
                        'tension' => 0.4, // Smooth curve
                        'order' => 0, // Render on top
                    ],
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
     * Get detailed referral hospital distribution.
     */
    /**
     * Get detailed referral hospital distribution.
     * Optimized to use pre-aggregated DailyReferralStat table.
     */
    public function getReferralStats(Request $request)
    {
        $startDate = $request->query('start_date', date('Y-m-d'));
        $endDate = $request->query('end_date', date('Y-m-d'));

        $cacheKey = $this->cacheKey('referral_stats', $startDate, $endDate);
        $isToday = ($startDate <= date('Y-m-d') && $endDate >= date('Y-m-d'));
        $ttl = $isToday ? 30 : 300;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            // Use pre-aggregated DailyReferralStat for much better performance
            // Group strictly by CODE to merge duplicates where names might differ slightly
            $stats = DailyReferralStat::whereDate('stat_date', '>=', $startDate)
                ->whereDate('stat_date', '<=', $endDate)
                ->select('ref_hosp_code as code', DB::raw('MAX(ref_hosp_name) as name'), DB::raw('SUM(count) as total'))
                ->groupBy('ref_hosp_code')
                ->orderByDesc('total')
                ->get();

            if ($stats->isEmpty()) {
                // Fallback to Scan-All-Visits query if aggregation table is empty
                $stats = Visit::whereDate('visit_date', '>=', $startDate)
                    ->whereDate('visit_date', '<=', $endDate)
                    ->whereNotNull('ref_hosp')
                    ->where('ref_hosp', '!=', '')
                    ->select('ref_hosp as code', DB::raw('MAX(ref_hosp_nm) as name'), DB::raw('COUNT(*) as total'))
                    ->groupBy('ref_hosp')
                    ->orderByDesc('total')
                    ->get();
            }

            return $stats->map(function($item) {
                return [
                    'code' => $item->code,
                    'name' => $item->name,
                    'count' => (int)$item->total
                ];
            });
        });
    }

    /**
     * Get a complete snapshot of dashboard data in one request.
     * Consolidates all core metrics for the dashboard.
     */
    public function getSnapshot(Request $request)
    {
        $startDate = $request->query('start_date', date('Y-m-d'));
        $endDate = $request->query('end_date', date('Y-m-d'));
        $period = $request->query('period', 'day');

        $cacheKey = $this->cacheKey('dashboard_snapshot', $startDate, $endDate, $period, $request->query('breakdown', 'none'));
        $isToday = ($startDate <= date('Y-m-d') && $endDate >= date('Y-m-d'));
        $ttl = $isToday ? 30 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($request) {
            return [
                'stats' => $this->getStats($request),
                'clinics' => $this->getClinicBreakdown($request),
                'pie' => $this->getPieStats($request),
                'comparison' => $this->getComparisonStats($request),
                'referrals' => $this->getReferralStats($request),
                'trends' => $this->getServiceTrends($request),
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    /**
     * Get list of MR numbers for patients not yet consulted.
     */
    public function getPendingPatients(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        \Illuminate\Support\Facades\Log::info("[Dashboard] Fetching pending patients", [
            'start' => $startDate,
            'end' => $endDate
        ]);

        $patients = Visit::whereDate('visit_date', '>=', $startDate)
            ->whereDate('visit_date', '<=', $endDate)
            ->where(function($q) {
                $q->where('visit_status', '!=', 'C')
                  ->orWhereNull('visit_status');
            })
            ->select('id', 'mr_number', 'visit_date', 'cons_time')
            ->orderBy('visit_date', 'asc')
            ->orderBy('cons_time', 'asc')
            ->orderBy('id', 'asc')
            ->limit(200)
            ->get();

        \Illuminate\Support\Facades\Log::info("[Dashboard] Found pending patients: " . $patients->count());

        return response()->json([
            'status' => 'success',
            'data' => $patients
        ]);
    }

    /**
     * Lightweight check to see if data has changed.
     * Super-fast polling endpoint for real-time detection.
     * No caching - always returns fresh data.
     */
    public function checkUpdates(Request $request)
    {
        $today = date('Y-m-d');
        
        // Get the latest visit ID (fastest way to detect new records)
        $latestVisitId = (int) Visit::max('id') ?? 0;
        
        // Get total visit count for today (detects deletes too)
        $todayVisitCount = (int) Visit::whereDate('visit_date', $today)->count();
        
        // Get total overall visit count (detects any changes)
        $totalVisitCount = (int) Visit::count();
        
        // Get the latest sync timestamp from aggregated stats
        $latestStat = DailyDashboardStat::select('updated_at')
            ->orderByDesc('updated_at')
            ->first();
        $latestClinic = ClinicStat::select('updated_at')
            ->orderByDesc('updated_at')
            ->first();
        
        $statTimestamp = $latestStat ? $latestStat->updated_at->timestamp : 0;
        $clinicTimestamp = $latestClinic ? $latestClinic->updated_at->timestamp : 0;
        
        // Combine all signals into a version hash
        $version = md5(
            $latestVisitId . '_' .
            $todayVisitCount . '_' .
            $totalVisitCount . '_' .
            $statTimestamp . '_' .
            $clinicTimestamp
        );

        return response()->json([
            'version' => $version,
            'latest_visit_id' => $latestVisitId,
            'today_count' => $todayVisitCount,
            'total_count' => $totalVisitCount,
            'stat_updated' => $statTimestamp,
            'clinic_updated' => $clinicTimestamp,
            'timestamp' => now()->toDateTimeString(),
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }
}
