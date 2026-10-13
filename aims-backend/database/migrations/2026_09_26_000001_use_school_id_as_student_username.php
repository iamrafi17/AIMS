<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conflict = DB::table('students as students')
            ->join('users as other', 'other.username', '=', 'students.student_id')
            ->whereColumn('other.id', '<>', 'students.user_id')
            ->exists();

        if ($conflict) {
            throw new RuntimeException('A school ID is already used as another account username.');
        }

        DB::table('students')->orderBy('id')->get(['user_id', 'student_id'])
            ->each(function (object $student): void {
                DB::table('users')->where('id', $student->user_id)
                    ->update(['username' => $student->student_id]);
            });
    }

    public function down(): void
    {
        DB::table('users')->whereIn('id', DB::table('students')->select('user_id'))
            ->update(['username' => null]);
    }
};
