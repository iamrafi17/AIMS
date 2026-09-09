<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('htes', function (Blueprint $table) {
            $table->string('normalized_name')->nullable()->after('name');
        });

        $usedNames = [];
        DB::table('htes')->orderBy('id')->get(['id', 'name'])->each(function (object $hte) use (&$usedNames): void {
            $base = Str::lower(preg_replace('/\s+/', ' ', trim((string) $hte->name)));
            $normalized = $base;

            if (isset($usedNames[$normalized])) {
                $normalized = mb_substr($base, 0, 230).'__legacy_'.$hte->id;
            }

            $usedNames[$normalized] = true;
            DB::table('htes')->where('id', $hte->id)->update(['normalized_name' => $normalized]);
        });

        Schema::table('htes', function (Blueprint $table) {
            $table->unique('normalized_name', 'htes_normalized_name_unique');
        });

        Schema::create('hte_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hte_id')->constrained('htes')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('college_id')->nullable()->constrained('colleges')->nullOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hte_id', 'program_id'], 'hte_program_unique');
            $table->index(['program_id', 'is_active'], 'hte_program_active_index');
        });

        DB::table('htes')
            ->whereNotNull('program_id')
            ->orderBy('id')
            ->get(['id', 'program_id', 'college_id', 'created_at', 'updated_at'])
            ->each(function (object $hte): void {
                DB::table('hte_programs')->insertOrIgnore([
                    'hte_id' => $hte->id,
                    'program_id' => $hte->program_id,
                    'college_id' => $hte->college_id,
                    'linked_by' => null,
                    'is_active' => true,
                    'created_at' => $hte->created_at ?? now(),
                    'updated_at' => $hte->updated_at ?? now(),
                ]);
            });

        Schema::create('hte_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hte_id')->constrained('htes')->cascadeOnDelete();
            $table->string('department_name');
            $table->string('normalized_name');
            $table->timestamps();

            $table->unique(['hte_id', 'normalized_name'], 'hte_department_name_unique');
            $table->index(['hte_id', 'department_name']);
        });

        Schema::create('supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hte_department_id')->constrained('hte_departments')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('normalized_name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hte_department_id', 'normalized_name'], 'supervisor_assignment_name_unique');
            $table->unique('user_id', 'supervisors_user_unique');
            $table->index(['full_name', 'hte_department_id']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('hte_department_id')->nullable()->after('hte_id')->constrained('hte_departments')->nullOnDelete();
            $table->foreignId('supervisor_record_id')->nullable()->after('hte_department_id')->constrained('supervisors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_record_id');
            $table->dropConstrainedForeignId('hte_department_id');
        });

        Schema::dropIfExists('supervisors');
        Schema::dropIfExists('hte_departments');
        Schema::dropIfExists('hte_programs');

        Schema::table('htes', function (Blueprint $table) {
            $table->dropUnique('htes_normalized_name_unique');
            $table->dropColumn('normalized_name');
        });
    }
};
