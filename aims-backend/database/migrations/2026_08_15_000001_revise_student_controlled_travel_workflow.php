<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_logs', function (Blueprint $table) {
            $table->foreignId('coordinator_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('departed_at')->nullable()->after('start_time');
            $table->string('status', 24)->default('active')->change();
            $table->index(['coordinator_id', 'status']);
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->string('point_type', 32)->change();
            $table->decimal('gps_accuracy', 10, 2)->nullable()->after('longitude');
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_log_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->restrictOnDelete();
            $table->string('report_number', 40)->unique();
            $table->text('remarks')->nullable();
            $table->string('status', 48)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('generated_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('program_head_validated_at')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamp('final_validated_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('travel_report_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('reviewer_role', 32);
            $table->string('action', 32);
            $table->string('reason')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at');
            $table->unsignedInteger('report_version');
            $table->timestamps();
            $table->index(['travel_report_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_report_validations');
        Schema::dropIfExists('travel_reports');

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->dropColumn('gps_accuracy');
            $table->enum('point_type', ['departure', 'arrival'])->change();
        });

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->dropIndex(['coordinator_id', 'status']);
            $table->dropConstrainedForeignId('coordinator_id');
            $table->dropColumn('departed_at');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active')->change();
        });
    }
};
