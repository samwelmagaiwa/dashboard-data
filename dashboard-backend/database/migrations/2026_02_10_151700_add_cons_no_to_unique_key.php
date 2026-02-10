<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration updates the unique constraint to include cons_no (consultation number).
     * This allows multiple visits by the same patient to the same clinic on the same day
     * to be counted separately, as long as they have different consultation numbers.
     */
    public function up(): void
    {
        // 1. First, clean up any duplicates that would violate the new constraint
        // Keep the record with the highest ID for each unique combination
        DB::statement("
            DELETE v1 FROM visits v1
            INNER JOIN (
                SELECT MAX(id) as max_id, mr_number, visit_num, visit_date, clinic_code, dept_code, cons_no
                FROM visits
                GROUP BY mr_number, visit_num, visit_date, clinic_code, dept_code, cons_no
                HAVING COUNT(*) > 1
            ) v2 ON v1.mr_number = v2.mr_number 
                AND v1.visit_num = v2.visit_num 
                AND v1.visit_date = v2.visit_date
                AND v1.clinic_code = v2.clinic_code
                AND v1.dept_code = v2.dept_code
                AND (v1.cons_no = v2.cons_no OR (v1.cons_no IS NULL AND v2.cons_no IS NULL))
            WHERE v1.id < v2.max_id
        ");

        Schema::table('visits', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEX FROM visits"))->pluck('Key_name');
            
            // Drop old unique constraint
            if ($indexes->contains('visits_encounter_unique')) {
                $table->dropUnique('visits_encounter_unique');
            }
            
            // Add new unique constraint including cons_no
            // This allows: same patient + same clinic + same day + DIFFERENT cons_no = separate visits
            $table->unique(
                ['mr_number', 'visit_num', 'visit_date', 'clinic_code', 'dept_code', 'cons_no'], 
                'visits_encounter_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropUnique('visits_encounter_unique');
            $table->unique(
                ['mr_number', 'visit_num', 'visit_date', 'clinic_code', 'dept_code'], 
                'visits_encounter_unique'
            );
        });
    }
};
