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
            $table->integer('matched_count')->default(0)->after('nhif_visits');
            $table->integer('mismatched_count')->default(0)->after('matched_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            $table->dropColumn(['matched_count', 'mismatched_count']);
        });
    }
};
