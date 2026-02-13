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
        Schema::create('daily_referral_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('ref_hosp_code', 20);
            $table->string('ref_hosp_name')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'ref_hosp_code']);
            $table->index('stat_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_referral_stats');
    }
};
