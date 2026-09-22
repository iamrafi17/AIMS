<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'attendance',
        'internship_requirements',
        'internship_tasks',
        'evaluations',
    ];

    public function up(): void
    {
        Schema::table('student_internships', function (Blueprint $table) {
            $table->unique(['id', 'student_id'], 'student_internships_id_student_unique');
        });

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['student_internship_id']);
                $table->foreign(
                    ['student_internship_id', 'student_id'],
                    $tableName.'_internship_student_foreign',
                )->references(['id', 'student_id'])->on('student_internships')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign($tableName.'_internship_student_foreign');
                $table->foreign('student_internship_id')
                    ->references('id')->on('student_internships')->restrictOnDelete();
            });
        }

        Schema::table('student_internships', function (Blueprint $table) {
            $table->dropUnique('student_internships_id_student_unique');
        });
    }
};
