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
        if (Schema::hasTable('daily_dashboard_stats')) {
            Schema::table('daily_dashboard_stats', function (Blueprint $table) {
                if (!collect(DB::select("SHOW INDEX FROM daily_dashboard_stats"))->pluck('Key_name')->contains('daily_dashboard_stats_stat_date_index')) {
                    $table->index('stat_date');
                }
            });
        }

        if (Schema::hasTable('clinic_stats')) {
            Schema::table('clinic_stats', function (Blueprint $table) {
                $indexes = collect(DB::select("SHOW INDEX FROM clinic_stats"))->pluck('Key_name');
                if (!$indexes->contains('clinic_stats_stat_date_index')) {
                    $table->index('stat_date');
                }
                if (!$indexes->contains('clinic_stats_clinic_code_index')) {
                    $table->index('clinic_code');
                }
            });
        }

        if (Schema::hasTable('sync_logs')) {
            Schema::table('sync_logs', function (Blueprint $table) {
                $indexes = collect(DB::select("SHOW INDEX FROM sync_logs"))->pluck('Key_name');
                if (!$indexes->contains('sync_logs_sync_date_index')) {
                    $table->index('sync_date');
                }
                if (!$indexes->contains('sync_logs_status_index')) {
                    $table->index('status');
                }
            });
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
            $table->dropIndex(['clinic_code']);
        });

        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropIndex(['sync_date']);
            $table->dropIndex(['status']);
        });
    }
};
