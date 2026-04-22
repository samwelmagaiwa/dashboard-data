<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('duplicate_visits') || $this->indexExists('duplicate_visits', 'duplicate_visits_unique')) {
            return;
        }

        Schema::table('duplicate_visits', function (Blueprint $table) {
            $table->unique(
                ['mr_number', 'visit_num', 'visit_date', 'clinic_code', 'dept_code', 'cons_no'],
                'duplicate_visits_unique'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('duplicate_visits') || !$this->indexExists('duplicate_visits', 'duplicate_visits_unique')) {
            return;
        }

        Schema::table('duplicate_visits', function (Blueprint $table) {
            $table->dropUnique('duplicate_visits_unique');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
