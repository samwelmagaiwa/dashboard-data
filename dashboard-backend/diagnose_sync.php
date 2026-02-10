<?php
/**
 * Data Sync Diagnostic Script
 * Compares remote API data with local database to identify discrepancies
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

$date = $argv[1] ?? date('Y-m-d');
$dateYmd = Carbon::parse($date)->format('Ymd');

echo "=== DATA SYNC DIAGNOSTIC REPORT ===\n";
echo "Date: {$date}\n";
echo "Generated: " . now() . "\n";
echo str_repeat("=", 50) . "\n\n";

// 1. Fetch from Remote API
$baseUrl = env('DASHBOARD_API_BASE_URL', 'http://192.168.235.250/labsms/swagger/dashboard');
$url = "{$baseUrl}/{$dateYmd}";
$username = env('DASHBOARD_API_USERNAME');
$password = env('DASHBOARD_API_PASSWORD');

echo "1. FETCHING FROM REMOTE API...\n";
echo "   URL: {$url}\n";

try {
    $response = Http::withBasicAuth($username, $password)
        ->timeout(60)
        ->get($url);
    
    if (!$response->successful()) {
        echo "   ERROR: API returned status " . $response->status() . "\n";
        exit(1);
    }
    
    $apiData = $response->json();
    $apiVisits = $apiData['data'] ?? [];
    echo "   Total records from API: " . count($apiVisits) . "\n\n";
    
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Get Local Database Count
echo "2. LOCAL DATABASE COUNTS...\n";
$dbTotal = DB::table('visits')->whereDate('visit_date', $date)->count();
echo "   Total visits in DB for {$date}: {$dbTotal}\n\n";

// 3. Clinic-wise comparison
echo "3. CLINIC-WISE COMPARISON...\n";
echo str_repeat("-", 80) . "\n";
printf("%-40s | %8s | %8s | %8s\n", "CLINIC NAME", "API", "DB", "DIFF");
echo str_repeat("-", 80) . "\n";

// Group API data by clinic
$apiByClinic = [];
foreach ($apiVisits as $visit) {
    $clinicName = trim($visit['clinicName'] ?? 'Unknown');
    if (!isset($apiByClinic[$clinicName])) {
        $apiByClinic[$clinicName] = [];
    }
    $apiByClinic[$clinicName][] = $visit;
}

// Get DB counts by clinic
$dbByClinic = DB::table('visits')
    ->whereDate('visit_date', $date)
    ->select('clinic_name', DB::raw('COUNT(*) as count'))
    ->groupBy('clinic_name')
    ->pluck('count', 'clinic_name')
    ->toArray();

// Combine all clinic names
$allClinics = array_unique(array_merge(array_keys($apiByClinic), array_keys($dbByClinic)));
sort($allClinics);

$discrepancies = [];
$totalApiCount = 0;
$totalDbCount = 0;

foreach ($allClinics as $clinic) {
    $apiCount = count($apiByClinic[$clinic] ?? []);
    $dbCount = $dbByClinic[$clinic] ?? 0;
    $diff = $dbCount - $apiCount;
    
    $totalApiCount += $apiCount;
    $totalDbCount += $dbCount;
    
    if ($diff != 0) {
        $discrepancies[$clinic] = [
            'api' => $apiCount,
            'db' => $dbCount,
            'diff' => $diff,
            'api_visits' => $apiByClinic[$clinic] ?? []
        ];
        printf("%-40s | %8d | %8d | %+8d ***\n", substr($clinic, 0, 40), $apiCount, $dbCount, $diff);
    } else {
        printf("%-40s | %8d | %8d | %8d\n", substr($clinic, 0, 40), $apiCount, $dbCount, $diff);
    }
}

echo str_repeat("-", 80) . "\n";
printf("%-40s | %8d | %8d | %+8d\n", "TOTAL", $totalApiCount, $totalDbCount, $totalDbCount - $totalApiCount);
echo "\n";

// 4. Analyze discrepancies
if (!empty($discrepancies)) {
    echo "4. DISCREPANCY ANALYSIS...\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($discrepancies as $clinic => $data) {
        if ($data['diff'] < 0) {
            // DB has fewer records than API - MISSING RECORDS
            echo "CLINIC: {$clinic}\n";
            echo "Issue: DB has {$data['db']} records but API has {$data['api']} ({$data['diff']} missing)\n\n";
            
            // Get unique keys from API
            $apiKeys = [];
            foreach ($data['api_visits'] as $v) {
                $key = $v['mrNumber'] . '|' . $v['visitNum'] . '|' . $v['clinicCode'] . '|' . $v['deptCode'];
                $apiKeys[$key] = $v;
            }
            
            // Get unique keys from DB
            $dbVisits = DB::table('visits')
                ->whereDate('visit_date', $date)
                ->where('clinic_name', $clinic)
                ->select('mr_number', 'visit_num', 'clinic_code', 'dept_code', 'cons_no')
                ->get();
            
            $dbKeys = [];
            foreach ($dbVisits as $v) {
                $key = $v->mr_number . '|' . $v->visit_num . '|' . $v->clinic_code . '|' . $v->dept_code;
                $dbKeys[$key] = $v;
            }
            
            // Find missing records
            $missingKeys = array_diff_key($apiKeys, $dbKeys);
            
            if (!empty($missingKeys)) {
                echo "Missing records in DB:\n";
                foreach (array_slice($missingKeys, 0, 10) as $key => $visit) {
                    echo "  - MR: {$visit['mrNumber']}, Visit#: {$visit['visitNum']}, Clinic: {$visit['clinicCode']}, Dept: {$visit['deptCode']}, ConsNo: " . ($visit['consNo'] ?? 'NULL') . "\n";
                }
                if (count($missingKeys) > 10) {
                    echo "  ... and " . (count($missingKeys) - 10) . " more\n";
                }
            }
            echo "\n";
        }
    }
    
    // 5. Check for potential causes
    echo "5. POTENTIAL CAUSES ANALYSIS...\n";
    echo str_repeat("-", 80) . "\n";
    
    // Check for duplicate keys in API data
    echo "\n5a. Checking for duplicate keys in API data...\n";
    $apiKeyCount = [];
    foreach ($apiVisits as $v) {
        $key = ($v['mrNumber'] ?? '') . '|' . ($v['visitNum'] ?? '') . '|' . ($v['visitDate'] ?? '') . '|' . ($v['clinicCode'] ?? '') . '|' . ($v['deptCode'] ?? '');
        if (!isset($apiKeyCount[$key])) {
            $apiKeyCount[$key] = 0;
        }
        $apiKeyCount[$key]++;
    }
    
    $duplicateApiKeys = array_filter($apiKeyCount, fn($c) => $c > 1);
    if (!empty($duplicateApiKeys)) {
        echo "    Found " . count($duplicateApiKeys) . " duplicate keys in API data!\n";
        echo "    This means the API sends multiple records with same unique key.\n";
        echo "    Our upsert will only keep the latest one, causing apparent 'missing' records.\n\n";
        
        echo "    Sample duplicates:\n";
        $i = 0;
        foreach ($duplicateApiKeys as $key => $count) {
            if ($i++ >= 5) break;
            echo "      Key: {$key} appears {$count} times\n";
        }
        
        $totalDuplicates = array_sum($duplicateApiKeys) - count($duplicateApiKeys);
        echo "\n    Total 'lost' records due to duplicates: {$totalDuplicates}\n";
    } else {
        echo "    No duplicate keys found in API data.\n";
    }
    
    // Check for NULL or empty values in key fields
    echo "\n5b. Checking for NULL/empty key fields in API data...\n";
    $invalidRecords = [];
    foreach ($apiVisits as $idx => $v) {
        $issues = [];
        if (empty($v['mrNumber'])) $issues[] = 'mrNumber empty';
        if (empty($v['visitNum'])) $issues[] = 'visitNum empty';
        if (empty($v['visitDate'])) $issues[] = 'visitDate empty';
        if (empty($v['clinicCode'])) $issues[] = 'clinicCode empty';
        if (empty($v['deptCode'])) $issues[] = 'deptCode empty';
        
        if (!empty($issues)) {
            $invalidRecords[] = [
                'index' => $idx,
                'issues' => $issues,
                'data' => $v
            ];
        }
    }
    
    if (!empty($invalidRecords)) {
        echo "    Found " . count($invalidRecords) . " records with NULL/empty key fields!\n";
        echo "    These records may be skipped or cause issues during sync.\n\n";
        foreach (array_slice($invalidRecords, 0, 5) as $r) {
            echo "    Record {$r['index']}: " . implode(', ', $r['issues']) . "\n";
        }
    } else {
        echo "    All API records have valid key fields.\n";
    }
    
    // Check for same patient visiting same clinic multiple times (different dept)
    echo "\n5c. Checking for same patient visiting same clinic multiple times...\n";
    $patientClinicVisits = [];
    foreach ($apiVisits as $v) {
        $key = ($v['mrNumber'] ?? '') . '|' . ($v['visitNum'] ?? '') . '|' . ($v['clinicCode'] ?? '');
        if (!isset($patientClinicVisits[$key])) {
            $patientClinicVisits[$key] = [];
        }
        $patientClinicVisits[$key][] = $v['deptCode'] ?? 'NULL';
    }
    
    $multiDeptVisits = array_filter($patientClinicVisits, fn($depts) => count($depts) > 1);
    if (!empty($multiDeptVisits)) {
        echo "    Found " . count($multiDeptVisits) . " cases where same patient visited same clinic but different departments.\n";
        echo "    This is normal and should be stored as separate records.\n";
    }
}

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "DIAGNOSIS COMPLETE\n";
echo str_repeat("=", 80) . "\n";
