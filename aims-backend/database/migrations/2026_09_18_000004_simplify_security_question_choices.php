<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Retain the original wording and IDs for accounts that already answered them.
            DB::table('security_questions')->whereIn('question', [
                'What nickname do your family and friends call you?',
                'What type of music do you like the most?',
            ])->update(['is_active' => false, 'updated_at' => now()]);

            foreach ([
                'What is your favorite food?',
                'What is your favorite sport?',
                'What is your favorite hobby?',
                'What is your favorite color?',
                'What is your favorite subject?',
                'What is your favorite movie?',
                'What is your favorite game?',
                'What is your favorite place?',
                'What is the name of your first pet?',
                'What is your favorite school activity?',
                'What is your nickname?',
                'Who is your favorite singer?',
            ] as $question) {
                DB::table('security_questions')->insertOrIgnore([
                    'question' => $question,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('security_questions')->whereIn('question', [
                'What is your nickname?',
                'Who is your favorite singer?',
            ])->update(['is_active' => true, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('security_questions')->whereIn('question', [
                'What nickname do your family and friends call you?',
                'What type of music do you like the most?',
            ])->update(['is_active' => true, 'updated_at' => now()]);

            // Preserve answers to the new questions when rolling back.
            DB::table('security_questions')->whereIn('question', [
                'What is your nickname?',
                'Who is your favorite singer?',
            ])->update(['is_active' => false, 'updated_at' => now()]);
        });
    }
};
