<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 9);
            $table->string('semester', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedTinyInteger('active_slot')->nullable()
                ->storedAs("CASE WHEN status = 'active' THEN 1 ELSE NULL END")->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_year', 'semester']);
        });

        // Serialize period changes, including activation when the list is empty.
        Schema::create('academic_period_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('academic_period_locks')->insert(['id' => 1]);

        foreach (['students', 'ojt_enrollments', 'academic_sections', 'report_templates'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('academic_period_id')->nullable()
                    ->constrained('academic_periods')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['report_templates', 'academic_sections', 'ojt_enrollments', 'students'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('academic_period_id'));
        }
        Schema::dropIfExists('academic_period_locks');
        Schema::dropIfExists('academic_periods');
    }
};
