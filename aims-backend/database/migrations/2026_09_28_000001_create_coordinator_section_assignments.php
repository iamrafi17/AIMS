<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordinator_section_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_section_id')->unique()->constrained('academic_sections')->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('coordinator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinator_section_assignments');
    }
};
