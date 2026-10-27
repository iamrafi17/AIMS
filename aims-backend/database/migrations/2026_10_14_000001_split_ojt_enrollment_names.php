<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('school_id');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
        });

        DB::table('ojt_enrollments')->orderBy('id')->chunkById(100, function ($enrollments): void {
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student_record_id
                    ? DB::table('students')->where('id', $enrollment->student_record_id)->first(['first_name', 'middle_name', 'last_name'])
                    : null;
                if ($student) {
                    $names = [$student->first_name, $student->middle_name, $student->last_name];
                } else {
                    $name = preg_replace('/\s+/u', ' ', trim($enrollment->full_name));
                    if (str_contains($name, ',')) {
                        [$last, $given] = array_map('trim', explode(',', $name, 2));
                    } else {
                        $parts = explode(' ', $name);
                        $last = count($parts) > 1 ? array_pop($parts) : '';
                        $given = implode(' ', $parts);
                    }
                    $givenParts = preg_split('/\s+/u', $given, -1, PREG_SPLIT_NO_EMPTY);
                    $middle = count($givenParts) > 1 && preg_match('/^[A-Za-z]\\.?$/', end($givenParts))
                        ? array_pop($givenParts) : null;
                    $names = [implode(' ', $givenParts), $middle, $last];
                }
                if (! filled($names[0]) || ! filled($names[2]) || mb_strlen($names[0]) > 100 || mb_strlen($names[2]) > 100 || mb_strlen((string) $names[1]) > 100) {
                    throw new RuntimeException('Cannot safely split OJT enrollment '.$enrollment->id.' name.');
                }
                DB::table('ojt_enrollments')->where('id', $enrollment->id)->update([
                    'first_name' => trim($names[0]),
                    'middle_name' => filled($names[1]) ? trim($names[1]) : null,
                    'last_name' => trim($names[2]),
                ]);
            }
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable(false)->change();
            $table->string('last_name', 100)->nullable(false)->change();
            $table->dropForeign(['registration_reviewed_by']);
            $table->dropColumn(['full_name', 'registration_feedback', 'registration_reviewed_by', 'registration_reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->string('full_name', 255)->nullable();
            $table->text('registration_feedback')->nullable();
            $table->foreignId('registration_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registration_reviewed_at')->nullable();
        });
        DB::table('ojt_enrollments')->orderBy('id')->chunkById(100, function ($enrollments): void {
            foreach ($enrollments as $enrollment) {
                DB::table('ojt_enrollments')->where('id', $enrollment->id)->update([
                    'full_name' => trim($enrollment->last_name.', '.$enrollment->first_name.' '.$enrollment->middle_name),
                ]);
            }
        });
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->string('full_name', 255)->nullable(false)->change();
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
