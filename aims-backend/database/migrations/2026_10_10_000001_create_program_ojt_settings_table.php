<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_ojt_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->decimal('required_hours', 8, 2);
            $table->date('ojt_start_date');
            $table->string('status', 20)->default('published');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['program_id', 'academic_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_ojt_settings');
    }
};
