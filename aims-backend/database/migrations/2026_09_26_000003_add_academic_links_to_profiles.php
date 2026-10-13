<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('college_id')->nullable()->after('user_id')->constrained('colleges')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained('programs')->nullOnDelete();
        });

        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->foreignId('college_id')->nullable()->after('user_id')->constrained('colleges')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained('programs')->nullOnDelete();
        });

        foreach (DB::table('students')->orderBy('id')->pluck('id') as $studentId) {
            $sectionId = DB::table('student_internships')
                ->where('student_id', $studentId)
                ->where('is_current', true)
                ->latest('id')
                ->value('academic_section_id');

            $sectionId ??= DB::table('ojt_enrollments')
                ->where('student_record_id', $studentId)
                ->latest('registered_at')
                ->latest('id')
                ->value('academic_section_id');

            $program = $sectionId ? DB::table('academic_sections')
                ->join('programs', 'programs.id', '=', 'academic_sections.program_id')
                ->where('academic_sections.id', $sectionId)
                ->first(['programs.id', 'programs.college_id']) : null;

            DB::table('students')->where('id', $studentId)->update([
                'program_id' => $program?->id,
                'college_id' => $program?->college_id,
            ]);
        }

        foreach (DB::table('employee_profiles')->orderBy('id')->pluck('id') as $profileId) {
            $program = DB::table('employee_program_assignments')
                ->join('programs', 'programs.id', '=', 'employee_program_assignments.program_id')
                ->where('employee_program_assignments.employee_profile_id', $profileId)
                ->where('employee_program_assignments.is_active', true)
                ->orderByDesc('employee_program_assignments.id')
                ->first(['programs.id', 'programs.college_id']);

            DB::table('employee_profiles')->where('id', $profileId)->update([
                'program_id' => $program?->id,
                'college_id' => $program?->college_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('college_id');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('college_id');
        });
    }
};
