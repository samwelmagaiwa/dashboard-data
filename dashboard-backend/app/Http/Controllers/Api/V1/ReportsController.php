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
    public function pending()
    {
        // 1. Pending by Clinic
        $byClinic = Visit::select('clinic_name', DB::raw('count(*) as count'))
            ->where('visit_status', '!=', 'C')
            ->groupBy('clinic_name')
            ->orderBy('count', 'desc')
            ->get();

        // 2. Aging (By Date) - Oldest First
        $aging = Visit::select('visit_date', DB::raw('count(*) as count'), DB::raw('DATEDIFF(NOW(), visit_date) as days_elapsed'))
            ->where('visit_status', '!=', 'C')
            ->whereDate('visit_date', '<', Carbon::today()) // Only past dates imply aging
            ->groupBy('visit_date')
            ->orderBy('visit_date', 'asc')
            ->get();

        // 3. List of Oldest Pending (Top 100)
        // Only fetch fields needed for display
        $list = Visit::where('visit_status', '!=', 'C')
            ->select('id', 'visit_date', 'patient_id', 'name', 'clinic_name', 'doctor_name', 'visit_status')
            ->orderBy('visit_date', 'asc')
            ->limit(100)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'by_clinic' => $byClinic,
                'aging' => $aging,
                'list' => $list
            ]
        ]);
    }
}
