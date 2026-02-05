<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clean up internal duplicates if any (identical encounters)
        DB::statement("
            DELETE v1 FROM visits v1
            INNER JOIN (
                SELECT MIN(id) as min_id, mr_number, visit_num, visit_date, clinic_code, dept_code
                FROM visits
                GROUP BY mr_number, visit_num, visit_date, clinic_code, dept_code
                HAVING COUNT(*) > 1
            ) v2 ON v1.mr_number = v2.mr_number 
                AND v1.visit_num = v2.visit_num 
                AND v1.visit_date = v2.visit_date
                AND v1.clinic_code = v2.clinic_code
                AND v1.dept_code = v2.dept_code
            WHERE v1.id > v2.min_id
        ");

        Schema::table('visits', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEX FROM visits"))->pluck('Key_name');
            
            // Drop old one if it exists
            if ($indexes->contains('visits_mr_number_visit_num_visit_date_clinic_code_unique')) {
                $table->dropUnique('visits_mr_number_visit_num_visit_date_clinic_code_unique');
            }
            if ($indexes->contains('visits_mr_number_visit_num_visit_date_unique')) {
                $table->dropUnique(['mr_number', 'visit_num', 'visit_date']);
            }
            
            // Add new one
            if (!$indexes->contains('visits_encounter_unique')) {
                $table->unique(['mr_number', 'visit_num', 'visit_date', 'clinic_code', 'dept_code'], 'visits_encounter_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropUnique('visits_encounter_unique');
            $table->unique(['mr_number', 'visit_num', 'visit_date', 'clinic_code'], 'visits_mr_number_visit_num_visit_date_clinic_code_unique');
        });
    }
};
