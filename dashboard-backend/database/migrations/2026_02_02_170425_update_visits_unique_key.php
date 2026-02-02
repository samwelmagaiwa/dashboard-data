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
        Schema::table('visits', function (Blueprint $table) {
            // Drop old composite unique key
            $table->dropUnique(['mr_number', 'visit_num', 'visit_date']);
            
            // Add new composite unique key including clinic_code
            $table->unique(['mr_number', 'visit_num', 'visit_date', 'clinic_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropUnique(['mr_number', 'visit_num', 'visit_date', 'clinic_code']);
            $table->unique(['mr_number', 'visit_num', 'visit_date']);
        });
    }
};
