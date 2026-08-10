<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_records', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
        });

        DB::table('approval_records')
            ->whereNull('submitted_at')
            ->update(['submitted_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        Schema::table('approval_records', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable(false)->change();
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->boolean('is_final_destination')->default(false)->after('point_type');
        });
    }

    public function down(): void
    {
        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->dropColumn('is_final_destination');
        });

        Schema::table('approval_records', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
