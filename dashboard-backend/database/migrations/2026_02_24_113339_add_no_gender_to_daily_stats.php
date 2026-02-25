<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_dashboard_stats', 'no_gender_count')) {
                $table->integer('no_gender_count')->default(0)->after('female_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            $table->dropColumn('no_gender_count');
        });
    }
};
