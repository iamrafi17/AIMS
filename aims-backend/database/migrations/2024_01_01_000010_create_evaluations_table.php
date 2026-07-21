<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users');
            $table->enum('evaluation_type', ['midterm', 'final']);
            $table->integer('work_quality')->checkBetween(1, 5);
            $table->integer('communication')->checkBetween(1, 5);
            $table->integer('professionalism')->checkBetween(1, 5);
            $table->integer('attendance')->checkBetween(1, 5);
            $table->integer('technical_skills')->checkBetween(1, 5);
            $table->integer('teamwork')->checkBetween(1, 5);
            $table->text('recommendations')->nullable();
            $table->text('feedback')->nullable();
            $table->enum('status', ['draft', 'submitted', 'finalized'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
