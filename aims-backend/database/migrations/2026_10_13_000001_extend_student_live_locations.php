<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_live_locations', function (Blueprint $table) {
            $table->foreignId('student_internship_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendance')->cascadeOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_live_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_id');
            $table->dropConstrainedForeignId('student_internship_id');
            $table->dropColumn(['recorded_at', 'received_at']);
        });
    }
};
