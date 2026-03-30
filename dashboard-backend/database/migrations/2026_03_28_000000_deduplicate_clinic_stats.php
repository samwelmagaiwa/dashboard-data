<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ClinicStat;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Deduplicate existing data
        // We find the latest entry for each (stat_date, clinic_code) and delete the others
        $duplicateIds = DB::select("
            SELECT id FROM clinic_stats 
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) as id 
                    FROM clinic_stats 
                    GROUP BY stat_date, clinic_code
                ) as tmp
            )
        ");

        if (!empty($duplicateIds)) {
            $ids = array_map(fn($row) => $row->id, $duplicateIds);
            // Chunking deletion to avoid SQL length limits
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('clinic_stats')->whereIn('id', $chunk)->delete();
            }
        }

        // 2. Add Unique Constraint to prevent future duplication
        Schema::table('clinic_stats', function (Blueprint $table) {
            // Drop existing indices if they exist (non-unique ones added previously)
            $indexes = collect(DB::select("SHOW INDEX FROM clinic_stats"))->pluck('Key_name');
            
            if ($indexes->contains('clinic_stats_stat_date_clinic_code_unique')) {
                $table->dropUnique('clinic_stats_stat_date_clinic_code_unique');
            }
            
            // Add the composite unique key
            $table->unique(['stat_date', 'clinic_code'], 'clinic_stats_date_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic_stats', function (Blueprint $table) {
            $table->dropUnique('clinic_stats_date_code_unique');
        });
    }
};
