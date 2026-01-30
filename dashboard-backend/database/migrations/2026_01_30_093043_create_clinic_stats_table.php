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
        Schema::create('clinic_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('clinic_code');
            $table->string('clinic_name');
            $table->integer('total_visits');
            $table->timestamps();

            // Indexes
            $table->index('stat_date');
            $table->index('clinic_code');
            $table->unique(['stat_date', 'clinic_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_stats');
    }
};
