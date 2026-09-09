<?php

namespace App\Services;

use App\Http\Controllers\Api\CoordinatorReportController;
use App\Http\Controllers\Api\ProgramHeadDashboardController;
use App\Http\Controllers\Api\VPAADashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportTemplateDatasetService
{
    public function __construct(private readonly ReportExportService $exports) {}

    public function build(Request $request, string $reportType): array
    {
        $role = $request->user()->role;
        $allowed = ReportTemplateService::reportTypesForRole($role);

        if (! array_key_exists($reportType, $allowed)) {
            throw ValidationException::withMessages([
                'report_type' => ['This report type is not available to your role.'],
            ]);
        }

        $dataset = match ($role) {
            'coordinator' => $this->exports->coordinator(
                app(CoordinatorReportController::class)->index($request)->getData(true),
                $reportType,
            ),
            'program_head' => $this->exports->programHead(
                app(ProgramHeadDashboardController::class)->index($request)->getData(true),
                $reportType,
            ),
            'vpaa' => $this->exports->vpaa(
                app(VPAADashboardController::class)->index($request)->getData(true),
                $reportType,
            ),
            default => throw ValidationException::withMessages([
                'role' => ['This role cannot generate template-based reports.'],
            ]),
        };

        $dataset['generated_by'] = $request->user()->name;

        return $dataset;
    }

    public function bindings(array $dataset, string $academicYear, string $semester): array
    {
        $bindings = [
            'university_name' => 'Marinduque State University',
            'campus' => 'Santa Cruz Campus',
            'report_title' => $dataset['title'] ?? 'Internship Report',
            'report_subtitle' => $dataset['subtitle'] ?? 'Official AIMS generated report',
            'program' => $dataset['scope'] ?? 'Authorized program',
            'academic_year' => $academicYear,
            'semester' => $semester,
            'date_generated' => $dataset['generated_at'] ?? now()->format('F j, Y'),
            'generated_by' => $dataset['generated_by'] ?? 'Authorized AIMS user',
            'report_period' => $dataset['period'] ?? 'All available records',
        ];

        foreach (($dataset['summary'] ?? []) as $label => $value) {
            $bindings[Str::snake(Str::ascii((string) $label))] = $this->stringValue($value);
        }

        $rows = [];
        foreach (($dataset['rows'] ?? []) as $row) {
            $mapped = [];
            foreach (($dataset['headers'] ?? []) as $index => $header) {
                $key = Str::snake(Str::ascii((string) $header));
                $mapped[$key] = $this->stringValue($row[$index] ?? '');
            }
            $rows[] = $mapped;
        }

        if ($rows !== []) {
            $bindings = array_merge($bindings, $rows[0]);
        }

        $aliases = [
            'student_name' => ['student', 'students', 'student_name'],
            'school_number' => ['school_number', 'student_id'],
            'hte_name' => ['hte', 'hte_destination', 'host_training_establishment'],
            'attendance_rate' => ['attendance', 'attendance_percentage', 'attendance_rate'],
            'required_hours' => ['required_hours'],
            'rendered_hours' => ['rendered_hours'],
            'remaining_hours' => ['remaining_hours'],
            'progress_percentage' => ['progress_percentage', 'ojt_progress_percentage'],
            'internship_status' => ['internship_status', 'status'],
            'deployment_batch' => ['deployment_batch', 'batch'],
            'deployment_date' => ['deployment_date', 'proposed_date'],
        ];

        foreach ($aliases as $target => $sources) {
            foreach ($sources as $source) {
                if (array_key_exists($source, $bindings)) {
                    $bindings[$target] = $bindings[$source];
                    break;
                }
            }
            $bindings[$target] ??= '';
        }

        return [
            'values' => $bindings,
            'rows' => $rows,
            'headers' => $dataset['headers'] ?? [],
            'summary' => $dataset['summary'] ?? [],
        ];
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return is_scalar($value) || $value === null ? (string) $value : json_encode($value);
    }
}
