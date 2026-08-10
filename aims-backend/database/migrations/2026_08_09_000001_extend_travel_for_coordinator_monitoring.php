<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_logs', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
            $table->foreignId('hte_id')->nullable()->after('created_by')->constrained('htes')->nullOnDelete();
            $table->timestamp('arrived_at')->nullable()->after('end_time');
            $table->string('arrival_photo_path', 500)->nullable()->after('arrived_at');
        });

        Schema::create('travel_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['travel_log_id', 'student_id']);
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->string('point_type', 20)->nullable()->after('checkpoint_name');
            $table->timestamp('captured_at')->nullable()->after('expected_at');
        });
    }

    public function down(): void
    {
        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->dropColumn(['point_type', 'captured_at']);
        });

        Schema::dropIfExists('travel_participants');

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hte_id');
            $table->dropColumn(['arrived_at', 'arrival_photo_path']);
        });
    }
};
