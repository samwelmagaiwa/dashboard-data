<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\SyncLog;
use App\Models\DailyDashboardStat;
use App\Models\ClinicStat;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\Doctor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncService
{
    protected static $cachedClinics = [];
    protected static $cachedDepts = [];
    protected static $cachedDoctors = [];
    /**
     * Unified sync for a specific date (Y-m-d)
     * Wrapped in transaction for integrity.
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

            $response = Http::withBasicAuth($username, $password)
                ->connectTimeout(10)
                ->timeout(60)
                ->retry(2, 500)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $visits = $data['data'] ?? [];
                
                // Use a single transaction for the whole date sync
                $syncedCount = DB::transaction(function () use ($visits, $date) {
                    $count = $this->bulkUpsertVisits($visits);
                    $this->updateAggregatedStats($date);
                    return $count;
                });

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
            Log::error("Sync failed for date {$date}: " . $e->getMessage());
            $syncLog->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Alias for backward compatibility in jobs
     */
    public function syncForDateOptimized($date)
    {
        return $this->syncForDate($date);
    }

    /**
     * Sync a range of dates in parallel batches.
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

        // Process in chunks of 20 parallel requests (Optimized for speed)
        $chunks = array_chunk($dates, 20);
        $username = env('DASHBOARD_API_USERNAME');
        $password = env('DASHBOARD_API_PASSWORD');

        foreach ($chunks as $chunk) {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($chunk, $username, $password) {
                $reqs = [];
                foreach ($chunk as $date) {
                    $dateYmd = Carbon::parse($date)->format('Ymd');
                    $url = "http://192.168.235.250/labsms/swagger/dashboard/{$dateYmd}";
                    $reqs[] = $pool->as($date)
                        ->withBasicAuth($username, $password)
                        ->connectTimeout(10)
                        ->timeout(60)
                        ->get($url);
                }
                return $reqs;
            });

            foreach ($responses as $date => $response) {
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
                        
                        $count = DB::transaction(function () use ($visits, $date) {
                            $c = $this->bulkUpsertVisits($visits);
                            $this->updateAggregatedStats($date);
                            return $c;
                        });
                        
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
     * Strictly validate and aggregate visits locally before dashboard view.
     */
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
                SUM(CASE WHEN pat_catg_nm LIKE "%NSSF%" THEN 1 ELSE 0 END) as nssf,
                SUM(CASE WHEN dept_code = "150" THEN 1 ELSE 0 END) as emergency
            ')
            ->first();

        DailyDashboardStat::updateOrCreate(
            ['stat_date' => $date],
            [
                'total_visits' => (int)($stats->total_visits ?? 0),
                'consulted' => (int)($stats->consulted ?? 0),
                'pending' => (int)($stats->pending ?? 0),
                'new_visits' => (int)($stats->new_visits ?? 0),
                'followups' => (int)($stats->followups ?? 0),
                'nhif_visits' => (int)($stats->nhif_visits ?? 0),
                'emergency' => (int)($stats->emergency ?? 0),
                'foreigner' => (int)($stats->foreigner ?? 0),
                'public' => (int)($stats->public ?? 0),
                'ippm_private' => (int)($stats->ippm_private ?? 0),
                'ippm_credit' => (int)($stats->ippm_credit ?? 0),
                'cost_sharing' => (int)($stats->cost_sharing ?? 0),
                'nssf' => (int)($stats->nssf ?? 0),
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
                    'clinic_name' => $item->clinic_name ?: 'Unknown Clinic',
                    'total_visits' => (int)$item->total_visits
                ]
            );
        }
        
        // Clear dashboard caches for this specific date
        $cacheKeys = [
            "dashboard_stats_{$date}_{$date}",
            "clinic_breakdown_{$date}_{$date}",
            "pie_stats_{$date}_{$date}",
            "comp_stats_{$date}_{$date}",
            // Also clear deprecated single-date keys just in case
            "dashboard_stats_{$date}",
            "clinic_breakdown_{$date}",
            "pie_stats_{$date}",
            "comp_stats_{$date}",
        ];

        foreach ($cacheKeys as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
    }

    /**
     * Bulk upsert visits with strict field cleaning.
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
            // Trim and validate all incoming fields
            $cleanData = collect($visitData)->map(function ($value) {
                if (is_string($value)) {
                    $v = trim($value);
                    return $v === '' ? null : $v;
                }
                return $value;
            })->all();

            $visitDateOrig = $cleanData['visitDate'] ?? null;
            if (!$visitDateOrig) continue; // Essential field

            // Format dates
            $visitDate = $visitDateOrig;
            if (strlen($visitDate) === 8 && is_numeric($visitDate)) {
                 $visitDate = Carbon::createFromFormat('Ymd', $visitDate)->toDateString();
            }

            $doctConsDt = $cleanData['doctConsDt'] ?? null;
            if ($doctConsDt && strlen($doctConsDt) === 8 && is_numeric($doctConsDt)) {
                 $doctConsDt = Carbon::createFromFormat('Ymd', $doctConsDt)->toDateString();
            }

            // Category logic
            $patCatgNm = $cleanData['patCatgNm'] ?? '';
            $isNhif = (stripos($patCatgNm, 'NHIF') !== false) ? 'Y' : 'N';

            // Master data prep
            if (!empty($cleanData['clinicCode'])) {
                $clinics[$cleanData['clinicCode']] = [
                    'clinic_code' => $cleanData['clinicCode'],
                    'clinic_name' => $cleanData['clinicName'] ?: 'Unknown Clinic'
                ];
            }
            if (!empty($cleanData['deptCode'])) {
                $departments[$cleanData['deptCode']] = [
                    'dept_code' => $cleanData['deptCode'],
                    'dept_name' => $cleanData['deptName'] ?: 'Unknown Dept'
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
                'visit_date' => $visitDate,
                'visit_type' => substr($cleanData['visitType'] ?? 'N', 0, 1),
                'doct_code' => $cleanData['doctCode'] ?? null,
                'cons_time' => $cleanData['consTime'] ?? null,
                'cons_no' => $cleanData['consNo'] ?? null,
                'clinic_code' => $cleanData['clinicCode'],
                'clinic_name' => $cleanData['clinicName'] ?: 'Unknown Clinic',
                'cons_doctor' => $cleanData['consDoctor'] ?? null,
                'visit_status' => substr($cleanData['visitStatus'] ?? 'P', 0, 1),
                'accomp_code' => $cleanData['accompCode'] ?? null,
                'doct_cons_dt' => $doctConsDt,
                'doct_cons_tm' => $cleanData['doctConsTm'] ?? null,
                'dept_code' => $cleanData['deptCode'],
                'dept_name' => $cleanData['deptName'] ?: 'Unknown Dept',
                'pat_catg' => $cleanData['patCatg'] ?? null,
                'ref_hosp' => $cleanData['refHosp'] ?? null,
                'nhi_yn' => substr($cleanData['nhiYn'] ?? 'N', 0, 1),
                'pat_catg_nm' => $patCatgNm,
                'status' => substr($cleanData['status'] ?? 'A', 0, 1),
                'is_nhif' => $isNhif,
                'gender' => isset($cleanData['patSex']) ? substr($cleanData['patSex'], 0, 1) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Master upserts - Filter out already cached items in this session
    $newClinics = array_diff_key($clinics, self::$cachedClinics);
    if (!empty($newClinics)) {
        Clinic::upsert(array_values($newClinics), ['clinic_code'], ['clinic_name']);
        self::$cachedClinics += $newClinics;
    }

    $newDepts = array_diff_key($departments, self::$cachedDepts);
    if (!empty($newDepts)) {
        Department::upsert(array_values($newDepts), ['dept_code'], ['dept_name']);
        self::$cachedDepts += $newDepts;
    }

    $newDoctors = array_diff_key($doctors, self::$cachedDoctors);
    if (!empty($newDoctors)) {
        Doctor::upsert(array_values($newDoctors), ['doctor_code'], ['doctor_code']);
        self::$cachedDoctors += $newDoctors;
    }

    // Visits upsert in chunks - Ensure we use the exact unique keys from migration
    foreach (array_chunk($preparedVisits, 1000) as $chunk) {
        Visit::upsert(
            $chunk,
            ['mr_number', 'visit_num', 'visit_date', 'clinic_code', 'dept_code'], // Matches visits_encounter_unique
            [
                'visit_type', 'doct_code', 'cons_time', 'cons_no', 
                'clinic_name', 'cons_doctor', 'visit_status', 'accomp_code', 
                'doct_cons_dt', 'doct_cons_tm', 'dept_name', 
                'pat_catg', 'ref_hosp', 'nhi_yn', 'pat_catg_nm', 'status', 
                'is_nhif', 'gender', 'updated_at'
            ]
        );
    }

        return count($preparedVisits);
    }
}
