<?php

namespace App\Services;

use App\Models\DailyDashboardStat;
use App\Models\Visit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GapDetectionService
{
    /**
     * Detect dates within a range that should have data but are missing
     * aggregate stats or contain unexpected zero totals.
     */
    public function detectGaps($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $period = CarbonPeriod::create($start, $end);

        $existingStats = DailyDashboardStat::whereBetween('stat_date', [$startDate, $endDate])
            ->get()
            ->keyBy(function ($item) {
                return $item->stat_date->format('Y-m-d');
            });

        $rawVisitCounts = Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->selectRaw('visit_date, COUNT(*) as total_visits')
            ->groupBy('visit_date')
            ->pluck('total_visits', 'visit_date');

        $gaps = [];

        foreach ($period as $date) {
            if ($this->shouldSkipDate($date)) {
                continue;
            }

            $dateStr = $date->format('Y-m-d');
            $rawVisits = (int) ($rawVisitCounts[$dateStr] ?? 0);

            if (!$existingStats->has($dateStr)) {
                $gaps[] = [
                    'date' => $dateStr,
                    'reason' => $rawVisits > 0
                        ? 'Raw visits exist, but aggregated dashboard stats are missing'
                        : 'No aggregate record found for an operational day',
                    'status' => 'MISSING',
                    'raw_visits' => $rawVisits,
                ];
            } else {
                $stat = $existingStats->get($dateStr);
                if ((int) $stat->total_visits === 0) {
                    $gaps[] = [
                        'date' => $dateStr,
                        'reason' => $rawVisits > 0
                            ? 'Aggregated stats show zero visits, but raw visits exist'
                            : 'Zero visits recorded on an operational day',
                        'status' => 'EMPTY',
                        'raw_visits' => $rawVisits,
                    ];
                }
            }
        }

        return $gaps;
    }

    public function countExpectedDataDays($startDate, $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $period = CarbonPeriod::create($start, $end);

        $count = 0;
        foreach ($period as $date) {
            if ($this->shouldSkipDate($date)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    public function countRecordedDataDays($startDate, $endDate): int
    {
        return DailyDashboardStat::whereBetween('stat_date', [$startDate, $endDate])
            ->get()
            ->filter(fn ($item) => !$this->shouldSkipDate($item->stat_date))
            ->count();
    }

    public function shouldSkipDate($date): bool
    {
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        if ($date->isFuture()) {
            return true;
        }

        return !$this->expectsDataForDate($date);
    }

    public function expectsDataForDate($date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $dateStr = $date->format('Y-m-d');

        if (in_array($dateStr, $this->excludedDates(), true)) {
            return false;
        }

        return !in_array($date->dayOfWeek, $this->nonOperationalWeekdays(), true);
    }

    private function nonOperationalWeekdays(): array
    {
        return config('dashboard.gap_detection.non_operational_weekdays', [Carbon::SUNDAY]);
    }

    private function excludedDates(): array
    {
        return config('dashboard.gap_detection.excluded_dates', []);
    }
}
