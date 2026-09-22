<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('academic_sections')
            ->whereNull('academic_period_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $section): void {
                $year = str_replace(['–', '—', ' '], ['-', '-', ''], trim((string) $section->academic_year));
                $matches = DB::table('academic_periods')->where('academic_year', $year)->get();
                $period = $matches->firstWhere('status', 'active') ?: ($matches->count() === 1 ? $matches->first() : null);

                if (! $period) {
                    throw new RuntimeException("Academic section {$section->id} cannot be normalized: its academic period is missing or ambiguous.");
                }

                DB::table('academic_sections')->where('id', $section->id)->update([
                    'academic_period_id' => $period->id,
                ]);
            });

        $mismatch = DB::table('academic_sections as sections')
            ->join('academic_periods as periods', 'periods.id', '=', 'sections.academic_period_id')
            ->whereColumn('sections.academic_year', '!=', 'periods.academic_year')
            ->first(['sections.id']);
        if ($mismatch) {
            throw new RuntimeException("Academic section {$mismatch->id} has an academic year that conflicts with its linked period.");
        }

        Schema::table('academic_sections', function (Blueprint $table) {
            $table->dropForeign(['academic_period_id']);
        });
        Schema::table('academic_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_period_id')->nullable(false)->change();
            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->restrictOnDelete();
            $table->dropColumn('academic_year');
        });
    }

    public function down(): void
    {
        Schema::table('academic_sections', function (Blueprint $table) {
            $table->string('academic_year', 20)->nullable()->after('year_level');
        });

        DB::table('academic_sections as sections')
            ->join('academic_periods as periods', 'periods.id', '=', 'sections.academic_period_id')
            ->update(['sections.academic_year' => DB::raw('periods.academic_year')]);

        Schema::table('academic_sections', function (Blueprint $table) {
            $table->string('academic_year', 20)->nullable(false)->change();
            $table->dropForeign(['academic_period_id']);
            $table->unsignedBigInteger('academic_period_id')->nullable()->change();
            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->restrictOnDelete();
        });
    }
};
