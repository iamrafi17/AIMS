<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_document_approvals', function (Blueprint $table) {
            $table->foreignId('dean_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dean_decided_at')->nullable();
            $table->text('dean_remarks')->nullable();
            $table->foreignId('vpaa_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vpaa_reviewed_at')->nullable();
        });

        // Existing pending folders still need a Dean decision before VPAA review.
        DB::table('student_document_approvals')->where('status', 'for_review')->update(['status' => 'for_dean_review']);
    }

    public function down(): void
    {
        DB::table('student_document_approvals')->where('status', 'for_dean_review')->update(['status' => 'for_review']);
        Schema::table('student_document_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dean_decided_by');
            $table->dropColumn(['dean_decided_at', 'dean_remarks']);
            $table->dropConstrainedForeignId('vpaa_reviewed_by');
            $table->dropColumn('vpaa_reviewed_at');
        });
    }
};
