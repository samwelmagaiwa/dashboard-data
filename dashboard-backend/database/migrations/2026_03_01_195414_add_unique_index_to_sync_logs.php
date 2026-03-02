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
        // 1. Clean up existing duplicates before adding index
        $duplicates = DB::table('sync_logs')
            ->select('sync_type', 'sync_date', DB::raw('MAX(id) as max_id'))
            ->groupBy('sync_type', 'sync_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('sync_logs')
                ->where('sync_type', $duplicate->sync_type)
                ->where('sync_date', $duplicate->sync_date)
                ->where('id', '<', $duplicate->max_id)
                ->delete();
        }

        // 2. Add the unique index
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->unique(['sync_type', 'sync_date'], 'sync_logs_unique_type_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropUnique('sync_logs_unique_type_date');
        });
    }
};
