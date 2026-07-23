<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('htes', function (Blueprint $table) {
            $table->boolean('geofence_enabled')->default(false)->after('geofence_radius');
            $table->time('default_am_start')->default('08:00')->after('geofence_enabled');
            $table->time('default_am_end')->default('12:00')->after('default_am_start');
            $table->time('default_pm_start')->default('13:00')->after('default_am_end');
            $table->time('default_pm_end')->default('17:00')->after('default_pm_start');
            $table->json('work_days')->nullable()->after('default_pm_end');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->json('work_days')->nullable()->after('official_pm_end');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('work_days');
        });

        Schema::table('htes', function (Blueprint $table) {
            $table->dropColumn([
                'geofence_enabled',
                'default_am_start',
                'default_am_end',
                'default_pm_start',
                'default_pm_end',
                'work_days',
            ]);
        });
    }
};
