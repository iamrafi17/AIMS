<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moas', function (Blueprint $table) {
            $table->enum('program_status', ['pending', 'endorsed', 'rejected'])->default('pending')->after('status');
            $table->text('program_feedback')->nullable()->after('program_status');
            $table->foreignId('program_reviewed_by')->nullable()->after('program_feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('program_reviewed_at')->nullable()->after('program_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('moas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_reviewed_by');
            $table->dropColumn(['program_status', 'program_feedback', 'program_reviewed_at']);
        });
    }
};
