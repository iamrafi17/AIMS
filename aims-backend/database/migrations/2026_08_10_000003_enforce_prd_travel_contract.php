<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('travel_logs', 'student_id')) {
            DB::table('travel_logs')
                ->whereNotNull('student_id')
                ->orderBy('id')
                ->each(function (object $travel): void {
                    DB::table('travel_participants')->insertOrIgnore([
                        'travel_log_id' => $travel->id,
                        'student_id' => $travel->student_id,
                        'created_at' => $travel->created_at ?? now(),
                        'updated_at' => $travel->updated_at ?? now(),
                    ]);
                });
        }

        DB::table('travel_logs')->where('status', 'arrived')->update(['status' => 'active']);
        DB::table('travel_logs')->where('status', 'scheduled')->update(['status' => 'cancelled']);

        $invalidPoints = DB::table('travel_checkpoints')
            ->where(function ($query) {
                $query->whereNull('point_type')
                    ->orWhereNull('captured_at')
                    ->orWhereNull('captured_by')
                    ->orWhereNotIn('point_type', ['departure', 'arrival']);
            })
            ->count();

        if ($invalidPoints > 0) {
            throw new RuntimeException(
                'Legacy planned checkpoints must be resolved before enforcing the PRD travel contract.',
            );
        }

        if (Schema::hasColumn('travel_logs', 'student_id')) {
            Schema::table('travel_logs', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
            });

            Schema::table('travel_logs', function (Blueprint $table) {
                $table->dropIndex('travel_logs_student_start_index');
                $table->dropColumn('student_id');
            });
        }

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active')->change();
        });

        if (Schema::hasColumn('travel_checkpoints', 'expected_at')) {
            Schema::table('travel_checkpoints', function (Blueprint $table) {
                $table->dropColumn('expected_at');
            });
        }

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->dropForeign(['captured_by']);
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->enum('point_type', ['departure', 'arrival'])->nullable(false)->change();
            $table->timestamp('captured_at')->nullable(false)->change();
            $table->foreignId('captured_by')->nullable(false)->change();
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->foreign('captured_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->dropForeign(['captured_by']);
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->string('point_type', 20)->nullable()->change();
            $table->timestamp('captured_at')->nullable()->change();
            $table->foreignId('captured_by')->nullable()->change();
            $table->timestamp('expected_at')->nullable()->after('checkpoint_name');
        });

        Schema::table('travel_checkpoints', function (Blueprint $table) {
            $table->foreign('captured_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('travel_logs', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('status', 20)->default('scheduled')->change();
            $table->index(['student_id', 'start_time'], 'travel_logs_student_start_index');
        });
    }
};
