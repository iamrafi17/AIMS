<?php

use App\Models\InternshipRequirement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable()->change();
            $table->enum('file_type', ['pdf', 'image', 'document'])->nullable()->change();
        });

        DB::table('internship_requirements')
            ->where('file_path', '')
            ->update(['file_path' => null, 'file_type' => null]);

        DB::table('internship_requirements')
            ->select(['id', 'student_id', 'requirement_name', 'file_path'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->student_id.'|'.$row->requirement_name)
            ->each(function ($records) {
                $keeper = $records
                    ->sortBy(fn ($record) => [$record->file_path ? 0 : 1, $record->id])
                    ->first();
                $duplicateIds = $records->pluck('id')->reject(fn ($id) => $id === $keeper->id);

                if ($duplicateIds->isNotEmpty()) {
                    DB::table('internship_requirements')->whereIn('id', $duplicateIds)->delete();
                }
            });

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'requirement_name'],
                'internship_requirements_student_requirement_unique'
            );
        });

        $now = now();
        $rows = DB::table('students')->pluck('id')
            ->flatMap(fn ($studentId) => collect(InternshipRequirement::OFFICIAL_REQUIREMENTS)->map(fn ($name) => [
                'student_id' => $studentId,
                'requirement_name' => $name,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]))
            ->all();

        if ($rows) {
            DB::table('internship_requirements')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        DB::table('internship_requirements')->whereNull('file_path')->delete();

        Schema::table('internship_requirements', function (Blueprint $table) {
            $table->dropUnique('internship_requirements_student_requirement_unique');
            $table->string('file_path', 500)->nullable(false)->change();
            $table->enum('file_type', ['pdf', 'image', 'document'])->nullable(false)->change();
        });
    }
};
