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
        Schema::table('clinic_stats', function (Blueprint $table) {
            $table->integer('consulted')->default(0)->after('total_visits');
            $table->integer('pending')->default(0)->after('consulted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic_stats', function (Blueprint $table) {
            $table->dropColumn(['consulted', 'pending']);
        });
    }
};
