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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('mr_number', 20);
            $table->string('visit_num', 10);
            $table->string('visit_type', 1)->nullable(); // N or F
            $table->date('visit_date');
            $table->string('doct_code', 10)->nullable();
            $table->time('cons_time')->nullable();
            $table->string('cons_no', 20)->nullable();
            $table->string('clinic_code', 10)->nullable();
            $table->string('clinic_name')->nullable();
            $table->string('cons_doctor')->nullable();
            $table->string('visit_status', 1)->nullable(); // C or P
            $table->string('accomp_code', 10)->nullable();
            $table->date('doct_cons_dt')->nullable();
            $table->time('doct_cons_tm')->nullable();
            $table->string('dept_code', 10)->nullable();
            $table->string('dept_name')->nullable();
            $table->string('pat_catg', 10)->nullable();
            $table->string('ref_hosp', 20)->nullable();
            $table->string('nhi_yn', 1)->nullable(); // Y or N
            $table->string('pat_catg_nm')->nullable();
            $table->string('status', 1)->default('A');
            $table->string('is_nhif', 1)->default('N'); // 'Y' or 'N' for dashboard
            $table->timestamps();

            // Unique constraint
            $table->unique(['mr_number', 'visit_num', 'visit_date']);

            // Indexes for Dashboard performance
            $table->index('visit_date');
            $table->index('visit_status');
            $table->index('visit_type');
            $table->index('clinic_code');
            $table->index('dept_code');
            $table->index('nhi_yn');
            $table->index('pat_catg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
