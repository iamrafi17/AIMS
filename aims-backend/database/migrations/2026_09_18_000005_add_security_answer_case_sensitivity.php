<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_security_questions', function (Blueprint $table) {
            // Existing hashes were lowercased before hashing and cannot recover their original case.
            $table->boolean('answer_case_sensitive')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('user_security_questions')->where('answer_case_sensitive', true)->exists()) {
            throw new RuntimeException('Cannot remove case sensitivity while case-sensitive security answers exist.');
        }

        Schema::table('user_security_questions', function (Blueprint $table) {
            $table->dropColumn('answer_case_sensitive');
        });
    }
};
