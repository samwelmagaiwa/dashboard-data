<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\SyncLog;
use App\Models\DailyDashboardStat;
use App\Models\ClinicStat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncService
{
    /**
     * Sync data for a specific date (Y-m-d)
     */
    public function syncForDate($date)
    {
        $dateYmd = Carbon::parse($date)->format('Ymd');
        $url = "http://192.168.235.250/labsms/swagger/dashboard/{$dateYmd}";
        
        $syncLog = SyncLog::create([
            'sync_type' => 'visits',
            'sync_date' => $date,
            'status' => 'FAILED',
            'records_synced' => 0,
            'started_at' => now(),
        ]);

        try {
            $username = env('DASHBOARD_API_USERNAME');
            $password = env('DASHBOARD_API_PASSWORD');

            $response = Http::withBasicAuth($username, $password)->timeout(30)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $visits = $data['data'] ?? [];
                $syncedCount = 0;

                DB::transaction(function () use ($visits, &$syncedCount) {
                    foreach ($visits as $visitData) {
                        $cleanData = collect($visitData)->map(function ($value) {
                            $trimmed = is_string($value) ? trim($value) : $value;
                            return $trimmed === '' ? null : $trimmed;
                        })->all();

                        // Update Master Tables
                        if (!empty($cleanData['clinicCode'])) {
                            \App\Models\Clinic::updateOrCreate(
                                ['clinic_code' => $cleanData['clinicCode']],
                                ['clinic_name' => $cleanData['clinicName']]
                            );
                        }

                        if (!empty($cleanData['deptCode'])) {
                            \App\Models\Department::updateOrCreate(
                                ['dept_code' => $cleanData['deptCode']],
                                ['dept_name' => $cleanData['deptName']]
                            );
                        }

                        if (!empty($cleanData['doctCode'])) {
                            \App\Models\Doctor::updateOrCreate(
                                ['doctor_code' => $cleanData['doctCode']]
                            );
                        }

                        Visit::updateOrCreate(
                            [
                                'mr_number' => $cleanData['mrNumber'],
                                'visit_num' => $cleanData['visitNum'],
                                'visit_date' => $cleanData['visitDate'],
                            ],
                            [
                                'visit_type' => $cleanData['visitType'],
                                'doct_code' => $cleanData['doctCode'] ?? null,
                                'cons_time' => $cleanData['consTime'] ?? null,
                                'cons_no' => $cleanData['consNo'] ?? null,
                                'clinic_code' => $cleanData['clinicCode'],
                                'clinic_name' => $cleanData['clinicName'],
                                'cons_doctor' => $cleanData['consDoctor'] ?? null,
                                'visit_status' => $cleanData['visitStatus'],
                                'accomp_code' => $cleanData['accompCode'] ?? null,
                                'doct_cons_dt' => $cleanData['doctConsDt'] ?? null,
                                'doct_cons_tm' => $cleanData['doctConsTm'] ?? null,
                                'dept_code' => $cleanData['deptCode'],
                                'dept_name' => $cleanData['deptName'],
                                'pat_catg' => $cleanData['patCatg'] ?? null,
                                'ref_hosp' => $cleanData['refHosp'] ?? null,
                                'nhi_yn' => $cleanData['nhiYn'] ?? null,
                                'pat_catg_nm' => $cleanData['patCatgNm'] ?? null,
                                'status' => $cleanData['status'] ?? 'A',
                                'is_nhif' => (stripos($cleanData['patCatgNm'] ?? '', 'NHIF') !== false) ? 'Y' : 'N',
                            ]
                        );
                        $syncedCount++;
                    }
                });

                $this->updateAggregatedStats($date);

                $syncLog->update([
                    'status' => 'SUCCESS',
                    'records_synced' => $syncedCount,
                    'finished_at' => now(),
                ]);

                return ['success' => true, 'count' => $syncedCount];
            }

            $syncLog->update([
                'error_message' => "API error: " . $response->status(),
                'finished_at' => now(),
            ]);
            return ['success' => false, 'error' => "API error: " . $response->status()];

        } catch (\Exception $e) {
            $syncLog->update([
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateAggregatedStats($date)
    {
        $stats = Visit::whereDate('visit_date', $date)
            ->selectRaw('
                COUNT(*) as total_visits,
                SUM(CASE WHEN visit_status = "C" THEN 1 ELSE 0 END) as consulted,
                SUM(CASE WHEN visit_status = "P" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN TRIM(visit_type) = "N" THEN 1 ELSE 0 END) as new_visits,
                SUM(CASE WHEN TRIM(visit_type) = "F" THEN 1 ELSE 0 END) as followups,
                SUM(CASE WHEN is_nhif = "Y" THEN 1 ELSE 0 END) as nhif_visits
            ')
            ->first();

        DailyDashboardStat::updateOrCreate(
            ['stat_date' => $date],
            [
                'total_visits' => $stats->total_visits ?? 0,
                'consulted' => $stats->consulted ?? 0,
                'pending' => $stats->pending ?? 0,
                'new_visits' => $stats->new_visits ?? 0,
                'followups' => $stats->followups ?? 0,
                'nhif_visits' => $stats->nhif_visits ?? 0,
            ]
        );

        $clinicData = Visit::whereDate('visit_date', $date)
            ->groupBy('clinic_code', 'clinic_name')
            ->select('clinic_code', 'clinic_name', DB::raw('COUNT(*) as total_visits'))
            ->get();

        foreach ($clinicData as $item) {
            ClinicStat::updateOrCreate(
                [
                    'stat_date' => $date,
                    'clinic_code' => $item->clinic_code
                ],
                [
                    'clinic_name' => $item->clinic_name,
                    'total_visits' => $item->total_visits
                ]
            );
        }
    }
}
