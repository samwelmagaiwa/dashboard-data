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
            $table->integer('neonate_count')->default(0)->after('unknown_gender_count');
            $table->integer('infant_count')->default(0)->after('neonate_count');
            $table->integer('child_count')->default(0)->after('infant_count');
            $table->integer('adolescent_count')->default(0)->after('child_count');
            $table->integer('adult_count')->default(0)->after('adolescent_count');
            $table->integer('elderly_count')->default(0)->after('adult_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_dashboard_stats', function (Blueprint $table) {
            $table->dropColumn([
                'neonate_count',
                'infant_count',
                'child_count',
                'adolescent_count',
                'adult_count',
                'elderly_count'
            ]);
        });
    }
};
