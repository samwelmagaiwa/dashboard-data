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
            $table->integer('foreigner')->default(0)->after('nhif_visits');
            $table->integer('public')->default(0)->after('foreigner');
            $table->integer('ippm_private')->default(0)->after('public');
            $table->integer('ippm_credit')->default(0)->after('ippm_private');
            $table->integer('cost_sharing')->default(0)->after('ippm_credit');
            $table->integer('waivers')->default(0)->after('cost_sharing');
            $table->integer('nssf')->default(0)->after('waivers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            $table->dropColumn(['foreigner', 'public', 'ippm_private', 'ippm_credit', 'cost_sharing', 'waivers', 'nssf']);
        });
    }
};
