<?php

use App\Models\AcademicPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropIndex('report_templates_scope_index');
            $table->enum('period_scope', ['all_periods', 'academic_period'])
                ->default('all_periods')
                ->after('program_id');
        });

        DB::table('report_templates')->orderBy('id')->get()->each(function (object $template): void {
            $periodId = $template->academic_period_id;
            $isGlobal = $template->academic_year === 'All Academic Years'
                && $template->semester === 'All Semesters';

            if (! $periodId && ! $isGlobal) {
                $year = AcademicPeriod::normalizeYear((string) $template->academic_year);
                $semester = AcademicPeriod::normalizeSemester((string) $template->semester);
                $matches = DB::table('academic_periods')
                    ->where('academic_year', $year)
                    ->where('semester', $semester)
                    ->limit(2)
                    ->get();

                if ($matches->count() !== 1) {
                    throw new RuntimeException("Report template {$template->id} cannot be normalized: its academic period is missing or ambiguous.");
                }
                $periodId = $matches->first()->id;
            }

            $scope = $periodId ? 'academic_period' : 'all_periods';
            $validation = json_decode((string) ($template->validation_result ?? ''), true);
            $validation = is_array($validation) ? $validation : [];
            $placeholders = json_decode((string) ($template->detected_placeholders ?? ''), true);
            if (is_array($placeholders)) {
                $validation['detected_placeholders'] = array_values($placeholders);
            } elseif (! isset($validation['detected_placeholders'])) {
                $validation['detected_placeholders'] = [];
            }

            DB::table('report_templates')->where('id', $template->id)->update([
                'period_scope' => $scope,
                'academic_period_id' => $periodId,
                'validation_result' => json_encode($validation, JSON_UNESCAPED_SLASHES),
                'active_key' => $template->is_active
                    ? ($template->program_id ?: 'university').'|'.$template->report_type.'|'.$scope.'|'.($periodId ?: 'all')
                    : null,
            ]);
        });

        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropColumn(['academic_year', 'semester', 'detected_placeholders']);
            $table->index(
                ['program_id', 'report_type', 'period_scope', 'academic_period_id'],
                'report_templates_scope_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropIndex('report_templates_scope_index');
            $table->string('academic_year', 20)->nullable()->after('version');
            $table->string('semester', 40)->nullable()->after('academic_year');
            $table->json('detected_placeholders')->nullable()->after('validation_result');
        });

        DB::table('report_templates')->orderBy('id')->get()->each(function (object $template): void {
            $period = $template->academic_period_id
                ? DB::table('academic_periods')->find($template->academic_period_id)
                : null;
            $validation = json_decode((string) ($template->validation_result ?? ''), true);
            $placeholders = is_array($validation)
                ? array_values($validation['detected_placeholders'] ?? [])
                : [];
            $year = $period?->academic_year ?? 'All Academic Years';
            $semester = $period
                ? match ($period->semester) {
                    '1st Semester' => 'First Semester',
                    '2nd Semester' => 'Second Semester',
                    default => $period->semester,
                }
            : 'All Semesters';

            DB::table('report_templates')->where('id', $template->id)->update([
                'academic_year' => $year,
                'semester' => $semester,
                'detected_placeholders' => json_encode($placeholders, JSON_UNESCAPED_SLASHES),
                'active_key' => $template->is_active
                    ? ($template->program_id ?: 'university').'|'.$template->report_type.'|'.$year.'|'.$semester
                    : null,
            ]);
        });

        Schema::table('report_templates', function (Blueprint $table) {
            $table->string('academic_year', 20)->nullable(false)->change();
            $table->string('semester', 40)->nullable(false)->change();
            $table->dropColumn('period_scope');
            $table->index(
                ['program_id', 'report_type', 'academic_year', 'semester'],
                'report_templates_scope_index',
            );
        });
    }
};
