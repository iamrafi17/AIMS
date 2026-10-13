<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('htes', function (Blueprint $table): void {
            $table->string('province', 100)->nullable()->after('address');
        });

        DB::table('htes')->whereRaw('LOWER(address) LIKE ?', ['%marinduque%'])->update(['province' => 'Marinduque']);

        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->string('status', 24)->default('active')->change();
            $table->index(['status', 'scheduled_at'], 'travel_logs_status_schedule_index');
        });
    }

    public function down(): void
    {
        DB::table('travel_logs')->where('status', 'scheduled')->update(['status' => 'cancelled']);
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->dropIndex('travel_logs_status_schedule_index');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active')->change();
        });

        Schema::table('htes', function (Blueprint $table): void {
            $table->dropColumn('province');
        });
    }
};
