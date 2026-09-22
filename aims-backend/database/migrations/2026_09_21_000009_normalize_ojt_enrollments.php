<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_COLUMNS = ['section', 'college_id', 'program_id', 'academic_period_id'];

    public function up(): void
    {
        if (DB::table('ojt_enrollments')->whereNull('academic_section_id')->exists()) {
            throw new RuntimeException('OJT enrollment normalization failed: an enrollment has no academic section.');
        }

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropForeign(['academic_section_id']);
            $table->dropForeign(['college_id']);
            $table->dropForeign(['program_id']);
            $table->dropForeign(['academic_period_id']);
            $table->dropIndex('ojt_enrollments_program_id_registered_at_index');
            $table->dropIndex('ojt_enrollments_section_registered_at_index');
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_section_id')->nullable(false)->change();
            $table->foreign('academic_section_id')
                ->references('id')->on('academic_sections')->restrictOnDelete();
            $table->index(['academic_section_id', 'registered_at'], 'ojt_enrollments_section_registration_index');
            $table->dropColumn(self::LEGACY_COLUMNS);
        });
    }

    public function down(): void
    {
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropIndex('ojt_enrollments_section_registration_index');
            $table->dropForeign(['academic_section_id']);
            $table->unsignedBigInteger('academic_section_id')->nullable()->change();
            $table->foreign('academic_section_id')->references('id')->on('academic_sections')->nullOnDelete();
            $table->string('section', 50)->nullable();
            $table->foreignId('college_id')->nullable()->constrained('colleges')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
        });

        DB::table('ojt_enrollments')->orderBy('id')->get()->each(function (object $enrollment): void {
            $section = DB::table('academic_sections')->where('id', $enrollment->academic_section_id)->first();
            $program = $section ? DB::table('programs')->where('id', $section->program_id)->first() : null;
            DB::table('ojt_enrollments')->where('id', $enrollment->id)->update([
                'section' => $section?->name,
                'program_id' => $section?->program_id,
                'college_id' => $program?->college_id,
                'academic_period_id' => $section?->academic_period_id,
            ]);
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->index(['program_id', 'registered_at']);
            $table->index(['section', 'registered_at']);
        });
    }
};
