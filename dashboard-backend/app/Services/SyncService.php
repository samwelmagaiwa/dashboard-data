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
            'status' => 'PROCESSING',
            'records_synced' => 0,
            'started_at' => now(),
        ]);

        try {
            $username = env('DASHBOARD_API_USERNAME');
            $password = env('DASHBOARD_API_PASSWORD');

            $response = Http::withBasicAuth($username, $password)->timeout(60)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $visits = $data['data'] ?? [];
                
                // Use the optimized bulk upsert method
                $syncedCount = $this->bulkUpsertVisits($visits);
                $this->updateAggregatedStats($date);

                $syncLog->update([
                    'status' => 'SUCCESS',
                    'records_synced' => $syncedCount,
                    'finished_at' => now(),
                ]);

                return ['success' => true, 'count' => $syncedCount];
            }

            $syncLog->update([
                'status' => 'FAILED',
                'error_message' => "API error: " . $response->status(),
                'finished_at' => now(),
            ]);
            return ['success' => false, 'error' => "API error: " . $response->status()];

        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync a range of dates in parallel batches to optimize speed.
     */
    public function syncDateRange($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        $results = [
            'total_synced_days' => 0,
            'errors' => []
        ];

        // Process in chunks of 5 to avoid overwhelming the server or memory
        $chunks = array_chunk($dates, 5);
        $username = env('DASHBOARD_API_USERNAME');
        $password = env('DASHBOARD_API_PASSWORD');

        foreach ($chunks as $chunk) {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($chunk, $username, $password) {
                $reqs = [];
                foreach ($chunk as $date) {
                    $dateYmd = Carbon::parse($date)->format('Ymd');
                    $url = "http://192.168.235.250/labsms/swagger/dashboard/{$dateYmd}";
                    $reqs[] = $pool->as($date)->withBasicAuth($username, $password)->timeout(60)->get($url);
                }
                return $reqs;
            });

            foreach ($responses as $date => $response) {
                // Determine status and log immediately
                $syncLog = SyncLog::create([
                    'sync_type' => 'visits',
                    'sync_date' => $date,
                    'status' => 'PENDING',
                    'records_synced' => 0,
                    'started_at' => now(),
                ]);

                if ($response instanceof \Exception) {
                     $syncLog->update([
                        'status' => 'FAILED',
                        'error_message' => $response->getMessage(),
                        'finished_at' => now(),
                    ]);
                    $results['errors'][$date] = $response->getMessage();
                    continue;
                }

                if ($response->successful()) {
                    try {
                        $data = $response->json();
                        $visits = $data['data'] ?? [];
                        $count = $this->processVisits($visits, $date);
                        
                        $syncLog->update([
                            'status' => 'SUCCESS',
                            'records_synced' => $count,
                            'finished_at' => now(),
                        ]);
                        $results['total_synced_days']++;
                    } catch (\Exception $e) {
                         $syncLog->update([
                            'status' => 'FAILED',
                            'error_message' => $e->getMessage(),
                            'finished_at' => now(),
                        ]);
                        $results['errors'][$date] = $e->getMessage();
                    }
                } else {
                    $syncLog->update([
                        'status' => 'FAILED',
                        'error_message' => "API Error: " . $response->status(),
                        'finished_at' => now(),
                    ]);
                    $results['errors'][$date] = "API Error: " . $response->status();
                }
            }
        }

        return $results;
    }

    /**
     * Process visits array and update DB
     */
    private function processVisits($visits, $date)
    {
        $syncedCount = 0;

        DB::transaction(function () use ($visits, $date, &$syncedCount) {
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
                        'clinic_code' => $cleanData['clinicCode'],
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
        
        return $syncedCount;
    }

    public function updateAggregatedStats($date)
    {
        $stats = Visit::whereDate('visit_date', $date)
            ->selectRaw('
                COUNT(*) as total_visits,
                SUM(CASE WHEN visit_status = "C" THEN 1 ELSE 0 END) as consulted,
                SUM(CASE WHEN (visit_status IS NULL OR visit_status != "C") THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN TRIM(visit_type) = "N" THEN 1 ELSE 0 END) as new_visits,
                SUM(CASE WHEN TRIM(visit_type) = "F" THEN 1 ELSE 0 END) as followups,
                SUM(CASE WHEN is_nhif = "Y" THEN 1 ELSE 0 END) as nhif_visits,
                SUM(CASE WHEN pat_catg = "016" THEN 1 ELSE 0 END) as foreigner,
                SUM(CASE WHEN pat_catg = "001" THEN 1 ELSE 0 END) as public,
                SUM(CASE WHEN pat_catg_nm LIKE "%IPPM%PRIVATE%" THEN 1 ELSE 0 END) as ippm_private,
                SUM(CASE WHEN pat_catg_nm LIKE "%IPPM%CREDIT%" THEN 1 ELSE 0 END) as ippm_credit,
                SUM(CASE WHEN pat_catg_nm LIKE "%COST%SHARING%" THEN 1 ELSE 0 END) as cost_sharing,
                SUM(CASE WHEN pat_catg_nm LIKE "%WAIVER%" THEN 1 ELSE 0 END) as waivers,
                SUM(CASE WHEN pat_catg_nm LIKE "%NSSF%" THEN 1 ELSE 0 END) as nssf,
                SUM(CASE WHEN dept_code = "150" THEN 1 ELSE 0 END) as emergency
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
                'emergency' => $stats->emergency ?? 0,
                'foreigner' => $stats->foreigner ?? 0,
                'public' => $stats->public ?? 0,
                'ippm_private' => $stats->ippm_private ?? 0,
                'ippm_credit' => $stats->ippm_credit ?? 0,
                'cost_sharing' => $stats->cost_sharing ?? 0,
                'waivers' => $stats->waivers ?? 0,
                'nssf' => $stats->nssf ?? 0,
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

    /**
     * Optimized sync for a single date using bulk upserts.
     * Called by SyncForDateJob.
     */
    public function syncForDateOptimized($date)
    {
        $dateYmd = Carbon::parse($date)->format('Ymd');
        $url = "http://192.168.235.250/labsms/swagger/dashboard/{$dateYmd}";
        
        $syncLog = SyncLog::create([
            'sync_type' => 'visits_optimized',
            'sync_date' => $date,
            'status' => 'PROCESSING',
            'records_synced' => 0,
            'started_at' => now(),
        ]);

        try {
            $username = env('DASHBOARD_API_USERNAME');
            $password = env('DASHBOARD_API_PASSWORD');

            $response = Http::withBasicAuth($username, $password)->timeout(120)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $visits = $data['data'] ?? [];
                
                $syncedCount = $this->bulkUpsertVisits($visits);

                $this->updateAggregatedStats($date);

                $syncLog->update([
                    'status' => 'SUCCESS',
                    'records_synced' => $syncedCount,
                    'finished_at' => now(),
                ]);

                return ['success' => true, 'count' => $syncedCount];
            }

            $syncLog->update([
                'status' => 'FAILED',
                'error_message' => "API error: " . $response->status(),
                'finished_at' => now(),
            ]);
            return ['success' => false, 'error' => "API error: " . $response->status()];

        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e; // Re-throw to fail the job so it can be retried
        }
    }

    /**
     * Bulk upsert visits using database bulk operations.
     * much faster than looping updateOrCreate.
     */
    private function bulkUpsertVisits(array $visits)
    {
        if (empty($visits)) {
            return 0;
        }

        $preparedVisits = [];
        $clinics = [];
        $departments = [];
        $doctors = [];
        $now = now();

        foreach ($visits as $visitData) {
            $cleanData = collect($visitData)->map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            })->all();
            
            foreach ($cleanData as $k => $v) {
                if ($v === '') $cleanData[$k] = null;
            }

            // Format dates locally because upsert bypasses Model mutators
            // API sends YYYYMMDD, DB needs YYYY-MM-DD
            $visitDate = $cleanData['visitDate'];
            if ($visitDate && strlen($visitDate) === 8 && is_numeric($visitDate)) {
                 $visitDate = \Carbon\Carbon::createFromFormat('Ymd', $visitDate)->toDateString();
            }

            $doctConsDt = $cleanData['doctConsDt'] ?? null;
            if ($doctConsDt && strlen($doctConsDt) === 8 && is_numeric($doctConsDt)) {
                 $doctConsDt = \Carbon\Carbon::createFromFormat('Ymd', $doctConsDt)->toDateString();
            }

            // Handle N/Y mapping for is_nhif
            $patCatgNm = $cleanData['patCatgNm'] ?? '';
            $isNhif = (stripos($patCatgNm, 'NHIF') !== false) ? 'Y' : 'N';

            if (!empty($cleanData['clinicCode'])) {
                $clinics[$cleanData['clinicCode']] = [
                    'clinic_code' => $cleanData['clinicCode'],
                    'clinic_name' => $cleanData['clinicName']
                ];
            }
            if (!empty($cleanData['deptCode'])) {
                $departments[$cleanData['deptCode']] = [
                    'dept_code' => $cleanData['deptCode'],
                    'dept_name' => $cleanData['deptName']
                ];
            }
            if (!empty($cleanData['doctCode'])) {
                $doctors[$cleanData['doctCode']] = [
                    'doctor_code' => $cleanData['doctCode']
                ];
            }

            $preparedVisits[] = [
                'mr_number' => $cleanData['mrNumber'],
                'visit_num' => $cleanData['visitNum'],
                'visit_date' => $visitDate, // Use formatted date
                'visit_type' => $cleanData['visitType'],
                'doct_code' => $cleanData['doctCode'] ?? null,
                'cons_time' => $cleanData['consTime'] ?? null,
                'cons_no' => $cleanData['consNo'] ?? null,
                'clinic_code' => $cleanData['clinicCode'],
                'clinic_name' => $cleanData['clinicName'],
                'cons_doctor' => $cleanData['consDoctor'] ?? null,
                'visit_status' => $cleanData['visitStatus'],
                'accomp_code' => $cleanData['accompCode'] ?? null,
                'doct_cons_dt' => $doctConsDt, // Use formatted date
                'doct_cons_tm' => $cleanData['doctConsTm'] ?? null,
                'dept_code' => $cleanData['deptCode'],
                'dept_name' => $cleanData['deptName'],
                'pat_catg' => $cleanData['patCatg'] ?? null,
                'ref_hosp' => $cleanData['refHosp'] ?? null,
                'nhi_yn' => $cleanData['nhiYn'] ?? null,
                'pat_catg_nm' => $patCatgNm,
                'status' => $cleanData['status'] ?? 'A',
                'is_nhif' => $isNhif,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($clinics)) {
            \App\Models\Clinic::upsert(array_values($clinics), ['clinic_code'], ['clinic_name']);
        }
        if (!empty($departments)) {
            \App\Models\Department::upsert(array_values($departments), ['dept_code'], ['dept_name']);
        }
        if (!empty($doctors)) {
            \App\Models\Doctor::upsert(array_values($doctors), ['doctor_code'], ['doctor_code']);
        }

        foreach (array_chunk($preparedVisits, 1000) as $chunk) {
            Visit::upsert(
                $chunk,
                ['mr_number', 'visit_num', 'visit_date', 'clinic_code'],
                [
                    'visit_type', 'doct_code', 'cons_time', 'cons_no', 'clinic_code', 
                    'clinic_name', 'cons_doctor', 'visit_status', 'accomp_code', 
                    'doct_cons_dt', 'doct_cons_tm', 'dept_code', 'dept_name', 
                    'pat_catg', 'ref_hosp', 'nhi_yn', 'pat_catg_nm', 'status', 
                    'is_nhif', 'updated_at'
                ]
            );
        }

        return count($preparedVisits);
    }
}
