<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->enum('work_mode', ['wfo', 'wfh', 'field']);
            $table->enum('session_type', ['full_day', 'am_half', 'pm_half']);
            $table->enum('status', ['present', 'absent', 'holiday', 'late']);
            $table->decimal('latitude_in', 10, 8)->nullable();
            $table->decimal('longitude_in', 11, 8)->nullable();
            $table->decimal('latitude_out', 10, 8)->nullable();
            $table->decimal('longitude_out', 11, 8)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->unique(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
