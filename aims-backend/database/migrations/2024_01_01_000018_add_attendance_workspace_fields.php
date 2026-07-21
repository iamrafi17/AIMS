<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('allow_past_attendance')->default(false)->after('required_ojt_hours');
            $table->time('official_am_start')->default('08:00')->after('allow_past_attendance');
            $table->time('official_am_end')->default('12:00')->after('official_am_start');
            $table->time('official_pm_start')->default('13:00')->after('official_am_end');
            $table->time('official_pm_end')->default('17:00')->after('official_pm_start');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->timestamp('ot_start')->nullable()->after('pm_time_out');
            $table->timestamp('ot_end')->nullable()->after('ot_start');
            $table->decimal('overtime_hours', 6, 2)->default(0)->after('ot_end');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['ot_start', 'ot_end', 'overtime_hours']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'allow_past_attendance',
                'official_am_start',
                'official_am_end',
                'official_pm_start',
                'official_pm_end',
            ]);
        });
    }
};
