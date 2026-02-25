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
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_dashboard_stats', 'unknown_gender_count')) {
                $table->integer('unknown_gender_count')->default(0)->after('no_gender_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            $table->dropColumn('unknown_gender_count');
        });
    }
};
