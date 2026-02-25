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
            $table->integer('unknown_gender_count')->default(0)->after('female_count');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->string('cons_doctor_name')->nullable()->after('cons_doctor');
            $table->integer('pat_age')->nullable()->after('cons_doctor_name');
            $table->string('prov_diag')->nullable()->after('pat_age');
            $table->string('final_diag')->nullable()->after('prov_diag');
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

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['cons_doctor_name', 'pat_age', 'prov_diag', 'final_diag']);
        });
    }
};
