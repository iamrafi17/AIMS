<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hte_batch_schedules', function (Blueprint $table) {
            $table->boolean('is_working')->default(true)->after('day_of_week');
        });

        DB::table('htes')->select(['id', 'work_days'])->orderBy('id')->chunkById(100, function ($htes): void {
            foreach ($htes as $hte) {
                $workDays = is_string($hte->work_days) ? json_decode($hte->work_days, true) : $hte->work_days;
                $workDays = is_array($workDays) ? $workDays : ['mon', 'tue', 'wed', 'thu', 'fri'];

                DB::table('hte_batch_schedules')
                    ->where('hte_id', $hte->id)
                    ->update(['is_working' => false]);
                DB::table('hte_batch_schedules')
                    ->where('hte_id', $hte->id)
                    ->whereIn('day_of_week', $workDays)
                    ->update(['is_working' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('hte_batch_schedules', function (Blueprint $table) {
            $table->dropColumn('is_working');
        });
    }
};
