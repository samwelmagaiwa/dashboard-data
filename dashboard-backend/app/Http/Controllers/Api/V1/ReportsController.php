<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Get pending visits statistics using 'visits' table directly.
     * Pending = visit_status != 'C' (Not Consulted)
     */
    public function pending(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());

        $v = config('dashboard.cache_version', 6);
        $cacheKey = "report_pending_{$startDate}_{$endDate}_v{$v}";
        $isToday = ($startDate <= date('Y-m-d') && $endDate >= date('Y-m-d'));
        $ttl = $isToday ? 60 : 3600;

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            // 1. Pending by Clinic
            $byClinic = Visit::select('clinic_name', DB::raw('count(*) as count'))
                ->where('visit_status', '!=', 'C')
                ->whereDate('visit_date', '>=', $startDate)
                ->whereDate('visit_date', '<=', $endDate)
                ->groupBy('clinic_name')
                ->orderBy('count', 'desc')
                ->get();

            // 2. Aging (By Date) - Oldest First
            $aging = Visit::select('visit_date', DB::raw('count(*) as count'), DB::raw('DATEDIFF(NOW(), visit_date) as days_elapsed'))
                ->where('visit_status', '!=', 'C')
                ->whereDate('visit_date', '>=', $startDate)
                ->whereDate('visit_date', '<=', $endDate)
                ->whereDate('visit_date', '<', Carbon::today()) 
                ->groupBy('visit_date')
                ->orderBy('visit_date', 'asc')
                ->get();

            // 3. List of Pending Consultations
            $list = Visit::where('visit_status', '!=', 'C')
                ->whereDate('visit_date', '>=', $startDate)
                ->whereDate('visit_date', '<=', $endDate)
                ->select(
                    'id',
                    'visit_date',
                    'mr_number',
                    'clinic_name',
                    'cons_doctor as doctor_code',
                    'cons_doctor_name as doctor_name',
                    'pat_age',
                    'prov_diag',
                    'final_diag',
                    'visit_status'
                )
                ->orderBy('clinic_name', 'asc')
                ->orderBy('visit_date', 'asc')
                ->limit(200)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'by_clinic' => $byClinic,
                    'aging' => $aging,
                    'list' => $list
                ]
            ]);
        });
    }
}
