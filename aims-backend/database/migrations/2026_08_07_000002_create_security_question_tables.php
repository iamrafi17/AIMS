<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_security_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('security_question_id')->constrained('security_questions')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('answer_hash');
            $table->timestamps();

            $table->unique(['user_id', 'position']);
            $table->unique(['user_id', 'security_question_id']);
        });

        $now = now();
        $questions = [
            'What is your favorite food?',
            'What is your favorite sport?',
            'What is your favorite hobby?',
            'What is your favorite color?',
            'What is your favorite subject?',
            'What is your favorite movie?',
            'What is your favorite game?',
            'What is your favorite place?',
            'What nickname do your family and friends call you?',
            'What is the name of your first pet?',
            'What is your favorite school activity?',
            'What type of music do you like the most?',
        ];

        DB::table('security_questions')->insert(
            array_map(fn (string $question) => [
                'question' => $question,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $questions),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_security_questions');
        Schema::dropIfExists('security_questions');
    }
};
