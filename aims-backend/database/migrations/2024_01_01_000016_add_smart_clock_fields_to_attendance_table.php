<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->timestamp('am_time_in')->nullable()->after('time_out');
            $table->timestamp('am_time_out')->nullable()->after('am_time_in');
            $table->timestamp('pm_time_in')->nullable()->after('am_time_out');
            $table->timestamp('pm_time_out')->nullable()->after('pm_time_in');
            $table->text('am_activity')->nullable()->after('pm_time_out');
            $table->text('pm_activity')->nullable()->after('am_activity');
            $table->json('am_time_in_location')->nullable()->after('pm_activity');
            $table->json('am_time_out_location')->nullable()->after('am_time_in_location');
            $table->json('pm_time_in_location')->nullable()->after('am_time_out_location');
            $table->json('pm_time_out_location')->nullable()->after('pm_time_in_location');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'am_time_in',
                'am_time_out',
                'pm_time_in',
                'pm_time_out',
                'am_activity',
                'pm_activity',
                'am_time_in_location',
                'am_time_out_location',
                'pm_time_in_location',
                'pm_time_out_location',
            ]);
        });
    }
};
