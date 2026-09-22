<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hte_batch_schedules', function (Blueprint $table) {
            $table->unsignedSmallInteger('grace_period_minutes')->default(10)->after('is_working');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->unsignedSmallInteger('am_late_minutes')->default(0)->after('am_time_in');
            $table->unsignedSmallInteger('pm_late_minutes')->default(0)->after('pm_time_in');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['am_late_minutes', 'pm_late_minutes']);
        });

        Schema::table('hte_batch_schedules', function (Blueprint $table) {
            $table->dropColumn('grace_period_minutes');
        });
    }
};
