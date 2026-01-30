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
        Schema::create('daily_dashboard_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->integer('total_visits');
            $table->integer('consulted');
            $table->integer('pending');
            $table->integer('new_visits');
            $table->integer('followups');
            $table->integer('nhif_visits');
            $table->timestamps();

            // Indexes
            $table->unique('stat_date');
            $table->index('stat_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_dashboard_stats');
    }
};
