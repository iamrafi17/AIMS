<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_internships', function (Blueprint $table): void {
            $table->foreignId('preferred_hte_id')->nullable()->constrained('htes')->nullOnDelete();
            $table->foreignId('preferred_hte_department_id')->nullable()->constrained('hte_departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_internships', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('preferred_hte_department_id');
            $table->dropConstrainedForeignId('preferred_hte_id');
        });
    }
};
