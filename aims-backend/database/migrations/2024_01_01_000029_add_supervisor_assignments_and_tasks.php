<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('hte_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->enum('supervisor_review_status', ['pending', 'approved', 'flagged'])->default('pending')->after('is_verified');
            $table->text('supervisor_feedback')->nullable()->after('supervisor_review_status');
            $table->foreignId('supervisor_reviewed_by')->nullable()->after('supervisor_feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
        });

        Schema::create('internship_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'cancelled'])->default('assigned');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_tasks');

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_reviewed_by');
            $table->dropColumn(['supervisor_review_status', 'supervisor_feedback', 'supervisor_reviewed_at']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
