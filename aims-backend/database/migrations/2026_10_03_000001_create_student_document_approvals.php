<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_internship_id')->unique()->constrained('student_internships')->cascadeOnDelete();
            $table->foreignId('endorsed_by')->constrained('users');
            $table->timestamp('endorsed_at');
            $table->string('status', 30)->default('for_review');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'endorsed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_document_approvals');
    }
};
