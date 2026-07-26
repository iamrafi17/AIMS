<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->index(['role', 'program_id']);
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('section')->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->index(['program_id', 'registered_at']);
        });

        Schema::table('htes', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->index(['program_id', 'is_active']);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->index(['program_id', 'date']);
        });

        Schema::table('moas', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->index(['program_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('moas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('college_id');
        });
        Schema::table('htes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('college_id');
        });
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('college_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('college_id');
        });
    }
};
