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
    private function getCacheVersion(): int
    {
        return config('dashboard.cache_version', 4);
    }

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
        return $prefix . '_' . implode('_', $parts) . '_v' . $this->getCacheVersion();
    }

    public function getStats(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        $comparison = $this->getComparisonPeriod(Carbon::parse($startDate), Carbon::parse($endDate));
        $cacheKey = $this->cacheKey('dashboard_stats_v2', $startDate, $endDate);
        $isToday = ($startDate === date('Y-m-d') && $endDate === date('Y-m-d'));
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate, $comparison) {
            $stats = $this->aggregateStats($startDate, $endDate);
            $prevStats = $this->aggregateStats($comparison['start']->toDateString(), $comparison['end']->toDateString());

            $expectedDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            $aggregatedDays = DailyDashboardStat::where('stat_date', '>=', $startDate)
                ->where('stat_date', '<=', $endDate)
                ->count();

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'compLabel' => $comparison['label'],
                'meta' => [
                    'expected_days' => $expectedDays,
                    'aggregated_days' => $aggregatedDays,
                    'is_fully_aggregated' => $aggregatedDays >= $expectedDays,
                    'is_syncing' => \App\Models\SyncLog::where('status', 'PROCESSING')->exists(),
                    'cached_at' => now()->toDateTimeString(),
                ],
                'stats' => $stats,
                'previous_stats' => $prevStats
            ];
        });
    }

    private function getComparisonPeriod(Carbon $start, Carbon $end)
    {
        $diffInDays = $start->diffInDays($end) + 1;
        $prevStart = $start->copy();
        $prevEnd = $end->copy();
        $label = '';

        // Year comparison
        if ($start->day == 1 && $start->month == 1 && $end->day == 31 && $end->month == 12) {
            $prevStart->subYear()->startOfYear();
            $prevEnd->subYear()->endOfYear();
            $label = "vs " . $prevStart->year;
        }
        // Month comparison
        elseif ($start->day == 1 && $end->isLastOfMonth() && $start->month == $end->month) {
            $prevStart->subMonth()->startOfMonth();
            $prevEnd->subMonth()->endOfMonth();
            $label = "vs " . $prevStart->format('M Y');
        }
        // Week comparison
        elseif ($diffInDays == 7) {
            $prevStart->subDays(7);
            $prevEnd->subDays(7);
            $label = "vs Prev Week";
        }
        // Day comparison
        elseif ($diffInDays == 1) {
            $prevStart->subDay();
            $prevEnd->subDay();
            $label = "vs Yesterday";
        }
        else {
            $prevStart->subDays($diffInDays);
            $prevEnd->subDays($diffInDays);
            $label = "vs Prev Period";
        }

        return [
            'start' => $prevStart,
            'end' => $prevEnd,
            'label' => $label
        ];
    }

    private function aggregateStats($startDate, $endDate)
    {
        $baseStats = DailyDashboardStat::where('stat_date', '>=', $startDate)
            ->where('stat_date', '<=', $endDate)
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
                SUM(emergency) as emergency_visits,
                SUM(duplicates) as duplicates,
                SUM(male_count) as male,
                SUM(female_count) as female,
                SUM(no_gender_count) as no_gender,
                SUM(unknown_gender_count) as unknown,
                SUM(neonate_count) as neonate,
                SUM(infant_count) as infant,
                SUM(child_count) as child,
                SUM(adolescent_count) as adolescent,
                SUM(adult_count) as adult,
                SUM(elderly_count) as elderly
            ')
            ->first();

        return [
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
            'duplicates' => (int)($baseStats->duplicates ?? 0),
            'gender' => [
                'male' => (int)($baseStats->male ?? 0),
                'female' => (int)($baseStats->female ?? 0),
                'no_gender' => (int)($baseStats->no_gender ?? 0),
                'unknown' => (int)($baseStats->unknown ?? 0),
            ],
            'age_groups' => [
                'neonate' => (int)($baseStats->neonate ?? 0),
                'infant' => (int)($baseStats->infant ?? 0),
                'child' => (int)($baseStats->child ?? 0),
                'adolescent' => (int)($baseStats->adolescent ?? 0),
                'adult' => (int)($baseStats->adult ?? 0),
                'elderly' => (int)($baseStats->elderly ?? 0),
            ],
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
    }

    /**
     * Get clinic-wise breakdown for a specific date range.
     */
    public function getClinicBreakdown(Request $request)
    {
        $startDate = $request->query('start_date', date('Y-m-d'));
        $endDate = $request->query('end_date', date('Y-m-d'));

        $cacheKey = $this->cacheKey('clinic_breakdown_v3', $startDate, $endDate);
        $isToday = ($startDate <= date('Y-m-d') && $endDate >= date('Y-m-d'));
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            $comparison = $this->getComparisonPeriod(Carbon::parse($startDate), Carbon::parse($endDate));
            $compLabel = $comparison['label'];

            $current = ClinicStat::where('stat_date', '>=', $startDate)
                ->where('stat_date', '<=', $endDate)
                ->selectRaw('clinic_name, SUM(total_visits) as total_visits')
                ->groupBy('clinic_name')
                ->orderByDesc('total_visits')
                ->limit(20)
                ->get();

            $prevCounts = ClinicStat::where('stat_date', '>=', $comparison['start']->toDateString())
                ->where('stat_date', '<=', $comparison['end']->toDateString())
                ->selectRaw('clinic_name, SUM(total_visits) as total_visits')
                ->groupBy('clinic_name')
                ->pluck('total_visits', 'clinic_name')
                ->toArray();

            $breakdown = [];
            foreach ($current as $row) {
                $clinicName = $row->clinic_name;
                $cur = (int) ($row->total_visits ?? 0);
                $prev = (int) ($prevCounts[$clinicName] ?? 0);

                $trend = 0.0;
                if ($prev > 0) {
                    $trend = (($cur - $prev) / $prev) * 100;
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
     * Get detailed visit-level breakdown for clinics.
     */
    public function getDetailedClinicVisits(Request $request)
    {
        $startDate = $request->query('start_date', date('Y-m-d'));
        $endDate = $request->query('end_date', date('Y-m-d'));

        // No caching for detailed visits to allow for responsiveness in search
        $query = Visit::whereDate('visit_date', '>=', $startDate)
            ->whereDate('visit_date', '<=', $endDate)
            ->select([
                'mr_number',
                'gender',
                'pat_age',
                'visit_type',
                'visit_date',
                'cons_time',
                'doct_code',
                'bill_doct_name',
                'cons_doctor',      // Attend Doctor Code
                'cons_doctor_name', // Attend Doctor Name
                'clinic_name',
                'clinic_code',
                'final_diag',
                'prov_diag'
            ]);

        $visits = $query->orderBy('clinic_name', 'asc')
            ->orderBy('visit_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $visits
        ]);
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
			// 1. Gender Distribution (Now from aggregated stats)
			$genderStats = \App\Models\DailyDashboardStat::where('stat_date', '>=', $startDate)
				->where('stat_date', '<=', $endDate)
				->selectRaw('SUM(total_visits) as total, SUM(male_count) as male, SUM(female_count) as female, SUM(no_gender_count) as no_gender, SUM(unknown_gender_count) as unknown')
				->first();

			// 2. Visit Type (Use aggregated daily_dashboard_stats)
			$visitTypeStats = \App\Models\DailyDashboardStat::where('stat_date', '>=', $startDate)
				->where('stat_date', '<=', $endDate)
				->selectRaw('SUM(total_visits) as total, SUM(new_visits) as new_visits, SUM(followups) as followups')
				->first();

			// 3. Age Group Distribution (New aggregated stats)
			$ageStats = \App\Models\DailyDashboardStat::where('stat_date', '>=', $startDate)
				->where('stat_date', '<=', $endDate)
				->selectRaw('SUM(total_visits) as total, SUM(neonate_count) as neonate, SUM(infant_count) as infant, SUM(child_count) as child, SUM(adolescent_count) as adolescent, SUM(adult_count) as adult, SUM(elderly_count) as elderly')
				->first();

            // --- FLEXIBLE FALLBACK LOGIC ---
            // Detect discrepancies between total visits and categorical counts (stale/missing aggregates)
            $genderSum = (int)($genderStats->male ?? 0) + (int)($genderStats->female ?? 0) + 
                         (int)($genderStats->no_gender ?? 0) + (int)($genderStats->unknown ?? 0);
            
            $visitTypeSum = (int)($visitTypeStats->new_visits ?? 0) + (int)($visitTypeStats->followups ?? 0);
            
            $ageSum = (int)($ageStats->neonate ?? 0) + (int)($ageStats->infant ?? 0) + 
                         (int)($ageStats->child ?? 0) + (int)($ageStats->adolescent ?? 0) +
                         (int)($ageStats->adult ?? 0) + (int)($ageStats->elderly ?? 0);
            
            $needsFallback = ($ageStats->total > 0 && $ageSum < $ageStats->total) || 
                             ($genderStats->total > 0 && $genderSum < $genderStats->total) ||
                             ($visitTypeStats->total > 0 && $visitTypeSum < $visitTypeStats->total) ||
                             (!$genderStats->male && !$genderStats->female);

            if ($needsFallback) {
                $rawStats = Visit::where('visit_date', '>=', $startDate)
                    ->where('visit_date', '<=', $endDate)
                    ->selectRaw('
                        SUM(CASE WHEN gender = "M" THEN 1 ELSE 0 END) as male,
                        SUM(CASE WHEN gender = "F" THEN 1 ELSE 0 END) as female,
                        SUM(CASE WHEN (gender IS NOT NULL AND gender != "M" AND gender != "F") THEN 1 ELSE 0 END) as no_gender,
                        SUM(CASE WHEN gender IS NULL THEN 1 ELSE 0 END) as unknown,
                        SUM(CASE WHEN TRIM(visit_type) = "N" THEN 1 ELSE 0 END) as new_visits,
                        SUM(CASE WHEN TRIM(visit_type) = "F" THEN 1 ELSE 0 END) as followups
                    ')
                    ->first();

                if ($rawStats && ($rawStats->male > 0 || $rawStats->female > 0 || $rawStats->unknown > 0 || $rawStats->no_gender > 0)) {
                    $genderStats = (object)[
                        'total' => (int)$rawStats->male + (int)$rawStats->female + (int)$rawStats->no_gender + (int)$rawStats->unknown,
                        'male' => $rawStats->male,
                        'female' => $rawStats->female,
                        'no_gender' => $rawStats->no_gender,
                        'unknown' => $rawStats->unknown
                    ];
                    $visitTypeStats = (object)[
                        'total' => (int)$rawStats->new_visits + (int)$rawStats->followups,
                        'new_visits' => $rawStats->new_visits,
                        'followups' => $rawStats->followups
                    ];
                }

                if ($ageSum < $genderStats->total) {
                    $rawAgeStats = Visit::where('visit_date', '>=', $startDate)
                        ->where('visit_date', '<=', $endDate)
                        ->selectRaw('
                            SUM(CASE WHEN pat_age IS NOT NULL AND CAST(SUBSTRING_INDEX(pat_age, ".", 1) AS UNSIGNED) = 0 AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(pat_age, ".", 2), ".", -1) AS UNSIGNED) = 0 AND CAST(SUBSTRING_INDEX(pat_age, ".", -1) AS UNSIGNED) BETWEEN 0 AND 28 THEN 1 ELSE 0 END) as neonate,
                            SUM(CASE WHEN pat_age IS NOT NULL AND (CAST(SUBSTRING_INDEX(pat_age, ".", 1) AS UNSIGNED) = 0) AND NOT (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(pat_age, ".", 2), ".", -1) AS UNSIGNED) = 0 AND CAST(SUBSTRING_INDEX(pat_age, ".", -1) AS UNSIGNED) BETWEEN 0 AND 28) THEN 1 ELSE 0 END) as infant,
                            SUM(CASE WHEN pat_age IS NOT NULL AND CAST(SUBSTRING_INDEX(pat_age, ".", 1) AS UNSIGNED) BETWEEN 1 AND 12 THEN 1 ELSE 0 END) as child,
                            SUM(CASE WHEN pat_age IS NOT NULL AND CAST(SUBSTRING_INDEX(pat_age, ".", 1) AS UNSIGNED) BETWEEN 13 AND 17 THEN 1 ELSE 0 END) as adolescent,
                            SUM(CASE WHEN pat_age IS NOT NULL AND CAST(SUBSTRING_INDEX(pat_age, ".", 1) AS UNSIGNED) BETWEEN 18 AND 59 THEN 1 ELSE 0 END) as adult,
                            SUM(CASE WHEN pat_age IS NOT NULL AND CAST(SUBSTRING_INDEX(pat_age, ".", 1) AS UNSIGNED) >= 60 THEN 1 ELSE 0 END) as elderly
                        ')
                        ->first();
                    
                    if ($rawAgeStats) {
                        $ageStats = (object)[
                            'neonate' => $rawAgeStats->neonate,
                            'infant' => $rawAgeStats->infant,
                            'child' => $rawAgeStats->child,
                            'adolescent' => $rawAgeStats->adolescent,
                            'adult' => $rawAgeStats->adult,
                            'elderly' => $rawAgeStats->elderly
                        ];
                    }
                }
            }

            return [
                'gender' => [
                    'male' => (int)($genderStats->male ?? 0),
                    'female' => (int)($genderStats->female ?? 0),
                    'no_gender' => (int)($genderStats->no_gender ?? 0),
                    'unknown' => (int)($genderStats->unknown ?? 0),
                ],
                'visit_type' => [
                    'new' => (int)($visitTypeStats->new_visits ?? 0),
                    'followup' => (int)($visitTypeStats->followups ?? 0),
                ],
                'age_groups' => [
                    'neonate' => (int)($ageStats->neonate ?? 0),
                    'infant' => (int)($ageStats->infant ?? 0),
                    'child' => (int)($ageStats->child ?? 0),
                    'adolescent' => (int)($ageStats->adolescent ?? 0),
                    'adult' => (int)($ageStats->adult ?? 0),
                    'elderly' => (int)($ageStats->elderly ?? 0),
                ]
            ];
        });
    }

    /**
     * Get comparison stats for Radar Chart (Current vs Previous Period)
     */
    public function getComparisonStats(Request $request)
    {
        $startDate = $request->query('start_date', date('Y-m-d'));
        $endDate = $request->query('end_date', date('Y-m-d'));

        $cacheKey = $this->cacheKey('comparison_radar_v3', $startDate, $endDate);
        $isToday = ($startDate <= date('Y-m-d') && $endDate >= date('Y-m-d'));
        $ttl = $isToday ? 60 : 600;

        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            $comparison = $this->getComparisonPeriod(Carbon::parse($startDate), Carbon::parse($endDate));

            $current = $this->aggregateStats($startDate, $endDate);
            $previous = $this->aggregateStats($comparison['start']->toDateString(), $comparison['end']->toDateString());

            // Radar Chart Labels (Categories)
            $labels = ['PUBLIC', 'NHIF', 'IPPM-PRV', 'IPPM-CRD', 'COST-SH', 'NSSF', 'FOREIGN'];

            return [
                'labels' => $labels,
                'period_labels' => [
                    'current' => Carbon::parse($startDate)->format('M d') . ' - ' . Carbon::parse($endDate)->format('M d'),
                    'previous' => $comparison['start']->format('M d') . ' - ' . $comparison['end']->format('M d'),
                ],
                'current' => [
                    (int)($current['categories']['public'] ?? 0),
                    (int)($current['categories']['nhif'] ?? 0),
                    (int)($current['categories']['ippm_private'] ?? 0),
                    (int)($current['categories']['ippm_credit'] ?? 0),
                    (int)($current['categories']['cost_sharing'] ?? 0),
                    (int)($current['categories']['nssf'] ?? 0),
                    (int)($current['categories']['foreigner'] ?? 0),
                ],
                'previous' => [
                    (int)($previous['categories']['public'] ?? 0),
                    (int)($previous['categories']['nhif'] ?? 0),
                    (int)($previous['categories']['ippm_private'] ?? 0),
                    (int)($previous['categories']['ippm_credit'] ?? 0),
                    (int)($previous['categories']['cost_sharing'] ?? 0),
                    (int)($previous['categories']['nssf'] ?? 0),
                    (int)($previous['categories']['foreigner'] ?? 0),
                ],
                'compLabel' => $comparison['label']
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
        $breakdownSuffix = $breakdown === 'monthly' ? '_monthly' : '';

        return Cache::remember($cacheKey . $breakdownSuffix, $ttl, function() use ($period, $startDate, $endDate, $start, $end, $breakdown) {
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
                        $key = $tempStart->format('o-W');
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
                    // Default Year View (Single Aggregated Bar)
                    $label = $start->format('Y');
                    $key = $start->format('Y');
                    $labels[] = $label;
                    $dataMap[$key] = [
                        'label' => $label,
                        'opd' => 0, 'emergency' => 0, 'consulted' => 0,
                        'not_consulted' => 0, 'new_visits' => 0, 'followups' => 0
                    ];
                    break;
            }

            // 2. Fetch data
            $query = DailyDashboardStat::where('stat_date', '>=', $start->toDateString())
                ->where('stat_date', '<=', $end->toDateString());

            switch ($effectivePeriod) {
                case 'monthly_breakdown':
                    $query->selectRaw('DATE_FORMAT(stat_date, "%Y-%m") as group_key');
                    break;
                case 'year':
                    $query->selectRaw('DATE_FORMAT(stat_date, "%Y") as group_key');
                    break;
                case 'week':
                    $query->selectRaw('DATE_FORMAT(stat_date, "%x-%v") as group_key'); // ISO Year-Week
                    break;
                default: // day, range, month
                    $query->selectRaw('DATE_FORMAT(stat_date, "%Y-%m-%d") as group_key');
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
            $stats = DailyReferralStat::where('stat_date', '>=', $startDate)
                ->where('stat_date', '<=', $endDate)
                ->select('ref_hosp_code as code', DB::raw('MAX(ref_hosp_name) as name'), DB::raw('SUM(count) as total'))
                ->groupBy('ref_hosp_code')
                ->orderByDesc('total')
                ->get();

            if ($stats->isEmpty()) {
                // Fallback to Scan-All-Visits query if aggregation table is empty
                $stats = Visit::where('visit_date', '>=', $startDate)
                ->where('visit_date', '<=', $endDate)
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
     * Get gender distribution statistics for a radar chart (by month).
     */
    public function getGenderRadarStats(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());

        $cacheKey = $this->cacheKey('gender_radar_stats', $startDate, $endDate);
        
        return Cache::remember($cacheKey, 3600, function() use ($startDate, $endDate) {
            $months = [];
            $tempStart = Carbon::parse($startDate)->startOfMonth();
            $tempEnd = Carbon::parse($endDate)->endOfMonth();

            while ($tempStart <= $tempEnd) {
                $monthKey = $tempStart->format('Y-m');
                $months[] = [
                    'key' => $monthKey,
                    'label' => $tempStart->format('M Y'),
                    'start' => $tempStart->copy()->startOfMonth()->toDateString(),
                    'end' => $tempStart->copy()->endOfMonth()->toDateString(),
                ];
                $tempStart->addMonth();
            }

            $datasets = [];
            foreach ($months as $month) {
                $stats = DailyDashboardStat::where('stat_date', '>=', $month['start'])
                    ->where('stat_date', '<=', $month['end'])
                    ->selectRaw('SUM(male_count) as male, SUM(female_count) as female, SUM(no_gender_count) as no_gender, SUM(unknown_gender_count) as unknown')
                    ->first();

                $datasets[] = [
                    'label' => $month['label'],
                    'male' => (int)($stats->male ?? 0),
                    'female' => (int)($stats->female ?? 0),
                    'other' => (int)($stats->no_gender ?? 0),
                    'not_specified' => (int)($stats->unknown ?? 0),
                ];
            }

            return $datasets;
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

        $patients = Visit::where('visit_date', '>=', $startDate)
            ->where('visit_date', '<=', $endDate)
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
     * Get list of duplicate visits captured during sync.
     */
    public function getDuplicateVisits(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $singleDate = $request->query('date');

        if (!$startDate || !$endDate) {
            $startDate = $singleDate ?? date('Y-m-d');
            $endDate = $startDate;
        }

        try {
            // Normalize dates using Carbon to handle YYYY-MM-DD, YYYYMMDD, etc.
            $startDate = \Carbon\Carbon::parse($startDate)->format('Y-m-d');
            $endDate = \Carbon\Carbon::parse($endDate)->format('Y-m-d');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("[Dashboard] Date parsing failed", ['start' => $startDate, 'end' => $endDate]);
            $startDate = date('Y-m-d');
            $endDate = $startDate;
        }

        \Illuminate\Support\Facades\Log::info("[Dashboard] Fetching duplicates normalized", [
            'start' => $startDate,
            'end' => $endDate
        ]);

        $duplicates = \App\Models\DuplicateVisit::select([
                'mr_number',
                'visit_num',
                'visit_date',
                'clinic_code',
                'clinic_name',
                'cons_time',
                'cons_no',
                'dept_code',
                'dept_name',
                'cons_doctor',
                'pat_catg_nm',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as occurrence_count'),
                \Illuminate\Support\Facades\DB::raw('MAX(synchronized_at) as latest_sync_at')
            ])
            ->where('visit_date', '>=', $startDate)
            ->where('visit_date', '<=', $endDate)
            ->groupBy([
                'mr_number',
                'visit_num',
                'visit_date',
                'clinic_code',
                'clinic_name',
                'cons_time',
                'cons_no',
                'dept_code',
                'dept_name',
                'cons_doctor',
                'pat_catg_nm'
            ])
            ->orderBy('occurrence_count', 'desc')
            ->orderBy('visit_date', 'desc')
            ->limit(300)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $duplicates
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
        $todayVisitCount = (int) Visit::where('visit_date', $today)->count();
        
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
