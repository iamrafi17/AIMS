<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_COLUMNS = [
        'default_am_start', 'default_am_end', 'default_pm_start', 'default_pm_end', 'work_days',
    ];

    public function up(): void
    {
        DB::table('htes')->orderBy('id')->get(['id', 'work_days'])->each(function (object $hte): void {
            $workingDays = is_string($hte->work_days) ? json_decode($hte->work_days, true) : $hte->work_days;
            if (is_array($workingDays) && $workingDays !== []
                && DB::table('hte_schedules')->where('hte_id', $hte->id)->count() !== 7) {
                throw new RuntimeException("HTE schedule backfill is incomplete for HTE {$hte->id}.");
            }
        });

        Schema::table('htes', fn (Blueprint $table) => $table->dropColumn(self::LEGACY_COLUMNS));
    }

    public function down(): void
    {
        Schema::table('htes', function (Blueprint $table) {
            $table->time('default_am_start')->nullable();
            $table->time('default_am_end')->nullable();
            $table->time('default_pm_start')->nullable();
            $table->time('default_pm_end')->nullable();
            $table->json('work_days')->nullable();
        });
    }
};
