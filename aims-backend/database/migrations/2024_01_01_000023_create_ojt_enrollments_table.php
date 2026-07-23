<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('school_id', 20)->unique();
            $table->string('full_name', 255);
            $table->string('section', 50);
            $table->enum('source', ['csv', 'manual'])->default('manual');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_record_id')->nullable()->unique()->constrained('students')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index(['section', 'registered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_enrollments');
    }
};
