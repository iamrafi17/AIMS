<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->enum('journal_status', ['pending', 'approved', 'rejected'])->default('pending')->after('pm_activity');
            $table->text('journal_feedback')->nullable()->after('journal_status');
            $table->foreignId('journal_reviewed_by')->nullable()->after('journal_feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('journal_reviewed_at')->nullable()->after('journal_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_reviewed_by');
            $table->dropColumn(['journal_status', 'journal_feedback', 'journal_reviewed_at']);
        });
    }
};
