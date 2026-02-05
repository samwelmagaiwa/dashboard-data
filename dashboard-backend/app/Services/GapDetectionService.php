<?php

namespace App\Services;

use App\Models\DailyDashboardStat;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GapDetectionService
{
    /**
     * Detect dates within a range that have no stats recorded or 0 visits.
     */
    public function detectGaps($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $period = CarbonPeriod::create($start, $end);
        
        $existingStats = DailyDashboardStat::whereBetween('stat_date', [$startDate, $endDate])
            ->get()
            ->keyBy(function($item) {
                return $item->stat_date->format('Y-m-d');
            });

        $gaps = [];

        foreach ($period as $date) {
            // Ignore future dates
            if ($date->isFuture()) {
                continue;
            }

            $dateStr = $date->format('Y-m-d');
            
            if (!$existingStats->has($dateStr)) {
                $gaps[] = [
                    'date' => $dateStr,
                    'reason' => 'No record found',
                    'status' => 'MISSING'
                ];
            } else {
                $stat = $existingStats->get($dateStr);
                if ($stat->total_visits == 0) {
                    $gaps[] = [
                        'date' => $dateStr,
                        'reason' => 'Zero visits recorded',
                        'status' => 'EMPTY'
                    ];
                }
            }
        }

        return $gaps;
    }
}
