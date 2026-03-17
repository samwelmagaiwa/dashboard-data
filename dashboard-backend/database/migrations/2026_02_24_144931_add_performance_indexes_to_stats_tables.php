<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!$this->hasIndex('daily_dashboard_stats', 'daily_dashboard_stats_stat_date_index')) {
            Schema::table('daily_dashboard_stats', function (Blueprint $table) {
                $table->index('stat_date');
            });
        }

        if (!$this->hasIndex('clinic_stats', 'clinic_stats_stat_date_index')) {
            Schema::table('clinic_stats', function (Blueprint $table) {
                $table->index('stat_date');
            });
        }

        if (!$this->hasIndex('clinic_stats', 'clinic_stats_stat_date_clinic_name_index')) {
            Schema::table('clinic_stats', function (Blueprint $table) {
                $table->index(['stat_date', 'clinic_name'], 'clinic_stats_stat_date_clinic_name_index');
            });
        }

        if (!$this->hasIndex('daily_referral_stats', 'daily_referral_stats_stat_date_index')) {
            Schema::table('daily_referral_stats', function (Blueprint $table) {
                $table->index('stat_date');
            });
        }

        if (!$this->hasIndex('daily_referral_stats', 'daily_referral_stats_stat_date_ref_hosp_code_index')) {
            Schema::table('daily_referral_stats', function (Blueprint $table) {
                $table->index(['stat_date', 'ref_hosp_code'], 'daily_referral_stats_stat_date_ref_hosp_code_index');
            });
        }
    }

    private function hasIndex($table, $indexName)
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        }
        catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            $table->dropIndex(['stat_date']);
        });

        Schema::table('clinic_stats', function (Blueprint $table) {
            $table->dropIndex(['stat_date']);
            $table->dropIndex('clinic_stats_stat_date_clinic_name_index');
        });

        Schema::table('daily_referral_stats', function (Blueprint $table) {
            $table->dropIndex(['stat_date']);
            $table->dropIndex('daily_referral_stats_stat_date_ref_hosp_code_index');
        });
    }
};
