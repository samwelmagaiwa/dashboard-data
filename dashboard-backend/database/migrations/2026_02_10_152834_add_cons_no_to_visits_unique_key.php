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
     * Adds cons_no to the unique key so that multiple consultations by the same patient
     * at the same clinic on the same day are stored as separate visit records.
     */
    public function up(): void
    {
        // Step 1: Fill NULL cons_no values with a generated value to ensure uniqueness
        // Using concat of visit_num and cons_time as fallback for NULL cons_no
        DB::statement("
            UPDATE visits 
            SET cons_no = CONCAT(visit_num, '-', COALESCE(TIME_FORMAT(cons_time, '%H%i%s'), id))
            WHERE cons_no IS NULL OR cons_no = ''
        ");

        // Step 2: Handle any remaining duplicates by keeping the latest record
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
                AND v1.cons_no = v2.cons_no
            WHERE v1.id < v2.max_id
        ");

        Schema::table('visits', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEX FROM visits"))->pluck('Key_name');
            
            // Drop old unique key if it exists
            if ($indexes->contains('visits_encounter_unique')) {
                $table->dropUnique('visits_encounter_unique');
            }
            
            // Create new unique key including cons_no
            // This ensures each consultation is stored separately
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
            $indexes = collect(DB::select("SHOW INDEX FROM visits"))->pluck('Key_name');
            
            // Drop the new unique key
            if ($indexes->contains('visits_encounter_unique')) {
                $table->dropUnique('visits_encounter_unique');
            }
            
            // Restore old unique key (without cons_no)
            $table->unique(
                ['mr_number', 'visit_num', 'visit_date', 'clinic_code', 'dept_code'], 
                'visits_encounter_unique'
            );
        });
    }
};
