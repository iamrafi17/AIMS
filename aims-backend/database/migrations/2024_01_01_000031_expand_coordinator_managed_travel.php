<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_logs', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
            $table->string('destination')->nullable()->after('session_code');
            $table->text('purpose')->nullable()->after('destination');
            $table->text('route_notes')->nullable()->after('purpose');
            $table->timestamp('scheduled_at')->nullable()->after('route_notes');
            $table->timestamp('start_time')->nullable()->change();
            $table->string('status', 20)->default('scheduled')->change();
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->unsignedSmallInteger('sequence')->default(1)->after('travel_log_id');
            $table->timestamp('expected_at')->nullable()->after('checkpoint_name');
        });

        Schema::create('travel_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_log_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40)->default('student');
            $table->string('contact', 40)->nullable();
            $table->string('relationship', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_companions');

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->dropColumn(['sequence', 'expected_at']);
        });

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['destination', 'purpose', 'route_notes', 'scheduled_at']);
        });
    }
};
