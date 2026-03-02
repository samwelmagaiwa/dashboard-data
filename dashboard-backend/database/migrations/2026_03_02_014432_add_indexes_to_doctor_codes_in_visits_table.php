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
            $table->index('doct_code', 'idx_visits_doct_code');
            $table->index('cons_doctor', 'idx_visits_cons_doctor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('idx_visits_doct_code');
            $table->dropIndex('idx_visits_cons_doctor');
        });
    }
};
