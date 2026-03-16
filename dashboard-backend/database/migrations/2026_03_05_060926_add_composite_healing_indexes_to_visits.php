<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Composite indexes for fast healing JOIN-UPDATE queries
            // (visit_date, doct_code) — used by bill_doct_name healer
            if (!$this->indexExists('visits', 'idx_visits_date_doct_code')) {
                $table->index(['visit_date', 'doct_code'], 'idx_visits_date_doct_code');
            }
            // (visit_date, cons_doctor) — used by cons_doctor_name healer
            if (!$this->indexExists('visits', 'idx_visits_date_cons_doctor')) {
                $table->index(['visit_date', 'cons_doctor'], 'idx_visits_date_cons_doctor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('idx_visits_date_doct_code');
            $table->dropIndex('idx_visits_date_cons_doctor');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return !empty($indexes);
    }
};
