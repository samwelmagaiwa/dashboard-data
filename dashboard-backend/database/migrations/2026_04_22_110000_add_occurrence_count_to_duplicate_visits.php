<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('duplicate_visits') || Schema::hasColumn('duplicate_visits', 'occurrence_count')) {
            return;
        }

        Schema::table('duplicate_visits', function (Blueprint $table) {
            $table->unsignedInteger('occurrence_count')->default(1)->after('pat_catg_nm');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('duplicate_visits') || !Schema::hasColumn('duplicate_visits', 'occurrence_count')) {
            return;
        }

        Schema::table('duplicate_visits', function (Blueprint $table) {
            $table->dropColumn('occurrence_count');
        });
    }
};
