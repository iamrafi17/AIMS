<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_sections', function (Blueprint $table) {
            $table->unique(['program_id', 'name', 'academic_period_id']);
            $table->dropUnique(['program_id', 'name', 'academic_year']);
        });
    }

    public function down(): void
    {
        if (DB::table('academic_sections')->select('program_id', 'name', 'academic_year')
            ->groupBy('program_id', 'name', 'academic_year')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot roll back while different semesters contain the same section name. Existing sections must be preserved.');
        }

        Schema::table('academic_sections', function (Blueprint $table) {
            $table->unique(['program_id', 'name', 'academic_year']);
            $table->dropUnique(['program_id', 'name', 'academic_period_id']);
        });
    }
};
