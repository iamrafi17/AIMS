<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->text('registration_feedback')->nullable()->after('registration_status');
            $table->foreignId('registration_reviewed_by')->nullable()->after('registration_feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('registration_reviewed_at')->nullable()->after('registration_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_reviewed_by');
            $table->dropColumn(['registration_feedback', 'registration_reviewed_at']);
        });
    }
};
