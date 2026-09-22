<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hte_department_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hte_department_id')->constrained('hte_departments')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hte_department_id', 'program_id'], 'hte_department_program_unique');
            $table->index(['program_id', 'is_active'], 'hte_department_program_active_index');
        });

        DB::table('hte_departments as departments')
            ->join('hte_programs as links', 'links.hte_id', '=', 'departments.hte_id')
            ->orderBy('departments.id')
            ->select([
                'departments.id as hte_department_id',
                'links.program_id',
                'links.linked_by',
                'links.is_active',
                'departments.created_at',
                'departments.updated_at',
            ])
            ->each(function (object $row): void {
                DB::table('hte_department_programs')->insertOrIgnore([
                    'hte_department_id' => $row->hte_department_id,
                    'program_id' => $row->program_id,
                    'linked_by' => $row->linked_by,
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            });

        Schema::table('hte_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('college_id');
        });

        Schema::table('htes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('college_id');
            $table->dropForeign(['program_id']);
            $table->dropIndex('htes_program_id_is_active_index');
            $table->dropColumn('program_id');
        });
    }

    public function down(): void
    {
        Schema::table('htes', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('id')->constrained('colleges')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained('programs')->nullOnDelete();
            $table->index(['program_id', 'is_active'], 'htes_program_id_is_active_index');
        });

        Schema::table('hte_programs', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('program_id')->constrained('colleges')->nullOnDelete();
        });

        DB::table('hte_programs as links')
            ->join('programs', 'programs.id', '=', 'links.program_id')
            ->select(['links.id', 'links.hte_id', 'links.program_id', 'programs.college_id'])
            ->orderBy('links.id')
            ->get()
            ->each(function (object $link): void {
                DB::table('hte_programs')->where('id', $link->id)->update([
                    'college_id' => $link->college_id,
                ]);

                DB::table('htes')
                    ->where('id', $link->hte_id)
                    ->whereNull('program_id')
                    ->update([
                        'college_id' => $link->college_id,
                        'program_id' => $link->program_id,
                    ]);
            });

        Schema::dropIfExists('hte_department_programs');
    }
};
