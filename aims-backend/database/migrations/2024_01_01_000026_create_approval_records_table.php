<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_records', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'created_at']);
        });

        $now = now();

        DB::table('internship_requirements')
            ->where('status', 'approved')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->chunkById(200, function ($requirements) use ($now) {
                DB::table('approval_records')->insert(
                    $requirements->map(fn ($requirement) => [
                        'subject_type' => 'document',
                        'subject_id' => $requirement->id,
                        'status' => 'pending',
                        'submitted_by' => $requirement->reviewed_by,
                        'created_at' => $requirement->reviewed_at ?? $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });

        DB::table('students')
            ->whereNotNull('hte_id')
            ->where('schedule_status', 'pending')
            ->orderBy('id')
            ->chunkById(200, function ($students) use ($now) {
                DB::table('approval_records')->insert(
                    $students->map(fn ($student) => [
                        'subject_type' => 'deployment',
                        'subject_id' => $student->id,
                        'status' => 'pending',
                        'created_at' => $student->updated_at ?? $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
    }
};
