<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moas', function (Blueprint $table): void {
            $table->foreignId('dean_endorsed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dean_endorsed_at')->nullable();
            $table->text('dean_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('moas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dean_endorsed_by');
            $table->dropColumn(['dean_endorsed_at', 'dean_remarks']);
        });
    }
};
