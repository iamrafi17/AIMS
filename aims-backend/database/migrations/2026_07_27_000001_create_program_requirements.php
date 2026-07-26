<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEFAULT_REQUIREMENTS = [
        'Birth Certificate',
        'Evaluation Checklist',
        'Medical Certificate',
        "Notarized Parent's/Guardian's Consent",
        "Parent's/Guardian's ID Card",
        'Notarized Training Contract',
        'Sample Accomplished Training Contract',
        'Medical Insurance ID',
        'Official Registration Form/Official List Enrolled Student (COR)',
        'Curriculum Vitae',
    ];

    public function up(): void
    {
        Schema::create('program_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('name');
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['program_id', 'key']);
            $table->unique(['program_id', 'name']);
            $table->index(['program_id', 'is_active', 'sort_order']);
        });

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->foreignId('program_requirement_id')
                ->nullable()
                ->after('student_id')
                ->constrained('program_requirements')
                ->nullOnDelete();
            $table->unique(
                ['student_id', 'program_requirement_id'],
                'internship_requirements_student_definition_unique'
            );
        });

        $now = now();
        foreach (DB::table('programs')->orderBy('id')->pluck('id') as $programId) {
            foreach (self::DEFAULT_REQUIREMENTS as $index => $name) {
                $definitionId = DB::table('program_requirements')->insertGetId([
                    'program_id' => $programId,
                    'key' => 'default-'.Str::slug($name),
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('internship_requirements')
                    ->where('requirement_name', $name)
                    ->whereIn('student_id', DB::table('students')->where('program_id', $programId)->select('id'))
                    ->update(['program_requirement_id' => $definitionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->dropUnique('internship_requirements_student_definition_unique');
            $table->dropConstrainedForeignId('program_requirement_id');
        });

        Schema::dropIfExists('program_requirements');
    }
};
