<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('category', 40)->default('general')->after('content');
            $table->string('attachment_path')->nullable()->after('target_audience');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->timestamp('scheduled_at')->nullable()->after('published_at');
            $table->timestamp('archived_at')->nullable()->after('scheduled_at');
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'attachment_path',
                'attachment_name',
                'scheduled_at',
                'archived_at',
            ]);
        });
    }
};
