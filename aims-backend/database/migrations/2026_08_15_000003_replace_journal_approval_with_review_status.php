<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->string('journal_status', 20)->default('pending')->change();
        });

        DB::table('attendance')
            ->whereIn('journal_status', ['approved', 'rejected'])
            ->update(['journal_status' => 'reviewed']);
    }

    public function down(): void
    {
        DB::table('attendance')
            ->where('journal_status', 'reviewed')
            ->update(['journal_status' => 'approved']);

        Schema::table('attendance', function (Blueprint $table) {
            $table->enum('journal_status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });
    }
};
