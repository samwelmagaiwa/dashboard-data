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
        Schema::create('duplicate_visits', function (Blueprint $table) {
            $table->id();
            $table->string('mr_number', 20)->index();
            $table->string('visit_num', 10);
            $table->date('visit_date')->index();
            $table->string('clinic_code', 10)->nullable();
            $table->string('clinic_name')->nullable();
            $table->string('cons_time')->nullable();
            $table->string('cons_no', 100)->nullable();
            $table->string('dept_code', 10)->nullable();
            $table->string('dept_name')->nullable();
            $table->string('cons_doctor')->nullable();
            $table->string('pat_catg_nm')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('synchronized_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_visits');
    }
};
