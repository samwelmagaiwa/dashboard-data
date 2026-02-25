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
        // 1. Optimize duplicate_visits for bulk upsert
        Schema::table('duplicate_visits', function (Blueprint $table) {
            // Drop individual indexes to replace with composite if necessary, 
            // but here we just add the unique constraint needed for upsert()
            $table->unique(
                ['mr_number', 'visit_num', 'visit_date', 'clinic_code', 'dept_code', 'cons_no'],
                'duplicate_visits_unique'
            );
        });

        // 2. Optimize visits for aggregation grouping
        Schema::table('visits', function (Blueprint $table) {
            $table->index(['visit_date', 'clinic_code'], 'idx_visits_date_clinic');
            $table->index(['visit_date', 'ref_hosp'], 'idx_visits_date_ref_hosp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duplicate_visits', function (Blueprint $table) {
            $table->dropUnique('duplicate_visits_unique');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('idx_visits_date_clinic');
            $table->dropIndex('idx_visits_date_ref_hosp');
        });
    }
};
