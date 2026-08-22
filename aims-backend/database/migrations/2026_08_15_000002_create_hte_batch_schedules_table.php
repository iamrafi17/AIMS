<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hte_batch_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hte_id')->constrained('htes')->cascadeOnDelete();
            $table->foreignId('academic_section_id')->constrained('academic_sections')->cascadeOnDelete();
            $table->string('day_of_week', 3);
            $table->time('am_start');
            $table->time('am_end');
            $table->time('pm_start');
            $table->time('pm_end');
            $table->timestamps();

            $table->unique(['hte_id', 'academic_section_id', 'day_of_week'], 'hte_batch_schedule_day_unique');
            $table->index(['academic_section_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hte_batch_schedules');
    }
};
