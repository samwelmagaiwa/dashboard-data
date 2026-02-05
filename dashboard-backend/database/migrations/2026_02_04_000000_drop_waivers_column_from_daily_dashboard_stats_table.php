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
            if (Schema::hasColumn('daily_dashboard_stats', 'waivers')) {
                $table->dropColumn('waivers');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_dashboard_stats', 'waivers')) {
                $table->integer('waivers')->default(0)->after('cost_sharing');
            }
        });
    }
};
