<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportService
{
    public function coordinator(array $data, string $report): array
    {
        $definitions = [
            'student_internship' => [
                'title' => 'Student Internship Report',
                'source' => 'students',
                'headers' => ['Student Number', 'Student Name', 'Academic Year', 'Semester', 'HTE', 'Working Schedule', 'Supervisor'],
                'fields' => ['studentId', 'name', 'academicYear', 'semester', 'hte', 'workingSchedule', 'supervisor'],
            ],
            'hte_deployment' => [
                'title' => 'Host Training Establishment Report',
                'source' => 'deployment_groups',
                'headers' => ['HTE', 'HTE Address', 'Department', 'Student Count', 'Working Schedule', 'Supervisor'],
                'fields' => ['hte', 'hteAddress', 'department', 'studentCount', 'workingSchedule', 'supervisor'],
            ],
            'travel_monitoring' => [
                'title' => 'Travel Monitoring Report',
                'source' => 'travel',
                'headers' => ['Travel Reference', 'Travel Date', 'Program', 'HTE / Destination', 'Students Involved', 'Departure', 'Arrival', 'Route / Checkpoints', 'Photo Evidence', 'Coordinator Remarks', 'Program Head Review', 'VPAA Validation', 'Report Status'],
                'fields' => ['id', 'date', 'program', 'destination', 'participants', 'departureTime', 'arrivalTime', 'checkpointSummary', 'photoEvidence', 'coordinatorRemarks', 'programHeadReview', 'vpaaValidation', 'reportStatus'],
            ],
        ];
        $definition = $definitions[$report];
        $records = $definition['source'] === 'deployment_groups'
            ? $this->coordinatorDeploymentGroups($data['students'] ?? [])
            : collect($data[$definition['source']] ?? []);
        $rows = $records->map(fn (array $row) => $this->row($row, $definition['fields']))->all();
        $program = trim(($data['program']['code'] ?? '').' - '.($data['program']['name'] ?? ''), ' -');

        return $this->dataset(
            $definition['title'],
            'Coordinator operational report',
            $program ?: 'Assigned program',
            $data['filters']['date_from'] ?? null,
            $data['filters']['date_to'] ?? null,
            ['Report records' => count($rows), 'Academic section / batch' => $data['selected_section']['name'] ?? 'All sections', 'Academic year' => $data['filters']['academic_year'] ?? 'All', 'Semester' => $data['filters']['semester'] ?? 'All'],
            $definition['headers'],
            $rows,
        );
    }

    public function programHead(array $data, string $report): array
    {
        $overview = $data['overview'] ?? [];
        $attendance = $data['attendance'] ?? [];
        $requirements = $data['requirements'] ?? [];
        $travel = $data['travel'] ?? [];
        $reviews = $data['pending_reviews'] ?? [];
        $program = $data['scope']['program'] ?? [];

        if ($report === 'hte_deployment_review') {
            $headers = ['School Number', 'Student', 'HTE', 'Deployment Batch', 'Proposed Date', 'Working Schedule', 'Requirements Readiness', 'Coordinator Submission', 'Review Status', 'Coordinator Remarks', 'Program Head Remarks'];
            $fields = ['student_id', 'student', 'hte', 'batch', 'proposed_date', 'working_schedule', 'requirements_readiness', 'submitted_at', 'review_status', 'coordinator_remarks', 'program_head_remarks'];
            $rows = collect($data['deployment_reviews'] ?? [])->map(fn (array $row) => $this->row($row, $fields))->all();
            $title = 'HTE Deployment Review Report';
        } elseif ($report === 'travel_monitoring_review') {
            $headers = ['Travel Reference', 'Students', 'HTE / Destination', 'Departure', 'Checkpoints', 'Arrival', 'Route Summary', 'Photo Evidence', 'Coordinator Remarks', 'Submission Date', 'Review Status'];
            $fields = ['reference', 'students', 'destination', 'departure', 'checkpoints', 'arrival', 'route_summary', 'photo_evidence', 'coordinator_remarks', 'submitted_at', 'review_status'];
            $rows = collect($data['travel_reviews'] ?? [])->map(fn (array $row) => $this->row($row, $fields))->all();
            $title = 'Travel Monitoring Review Report';
        } else {
            $headers = ['Section', 'Students', 'Active Interns', 'Average OJT Progress %', 'Attendance Rate %'];
            $rows = collect($data['analytics']['section_performance'] ?? [])->map(fn (array $row) => [
                $row['section'] ?? '', $row['students'] ?? 0, $row['active'] ?? 0, $row['progress'] ?? 0, $row['attendance_rate'] ?? 0,
            ])->all();
            $title = 'Program Internship Summary Report';
        }

        return $this->dataset(
            $title,
            'Program Head summarized report',
            trim(($program['code'] ?? '').' - '.($program['name'] ?? ''), ' -') ?: 'Assigned program',
            $data['filters']['date_from'] ?? null,
            $data['filters']['date_to'] ?? null,
            [
                'Total students' => $overview['total_students'] ?? 0,
                'Deployed students' => $overview['deployed'] ?? 0,
                'Average OJT progress' => ($overview['average_progress'] ?? 0).'%',
                'Attendance rate' => ($attendance['rate'] ?? 0).'%',
                'Requirements compliance' => ($requirements['completion_rate'] ?? 0).'%',
                'Pending reviews' => $reviews['total'] ?? 0,
                'Travel completion' => ($travel['completion_rate'] ?? 0).'%',
                'Academic section' => $data['scope']['selected_section']['name'] ?? 'All sections',
            ],
            $headers,
            $rows,
        );
    }

    public function vpaa(array $data, string $report): array
    {
        $overview = $data['overview'] ?? [];
        $compliance = $data['compliance'] ?? [];
        if ($report === 'hte_deployment_validation') {
            $title = 'HTE Deployment Validation Report';
            $headers = ['Deployment Batch', 'Program', 'HTE', 'School Number', 'Student', 'Proposed Date', 'Working Schedule', 'Coordinator Submission', 'Program Head Review', 'Program Head Remarks', 'VPAA Validation', 'Final Deployment Status'];
            $fields = ['batch', 'program', 'hte', 'student_id', 'student', 'proposed_date', 'working_schedule', 'coordinator_submission', 'program_head_review', 'program_head_remarks', 'vpaa_validation', 'deployment_status'];
            $rows = collect($data['deployment_validations'] ?? [])->map(fn (array $row) => $this->row($row, $fields))->all();
        } elseif ($report === 'travel_monitoring_validation') {
            $title = 'Travel Monitoring Validation Report';
            $headers = ['Travel Reference', 'Program', 'HTE / Destination', 'Students', 'Travel Date', 'Departure / Arrival', 'Route / Checkpoints', 'Photo Evidence', 'Coordinator Submission', 'Program Head Review', 'Program Head Remarks', 'VPAA Validation', 'Approval Status'];
            $fields = ['reference', 'program', 'destination', 'students', 'travel_date', 'departure_arrival', 'route_checkpoints', 'photo_evidence', 'coordinator_submission', 'program_head_review', 'program_head_remarks', 'vpaa_validation', 'approval_status'];
            $rows = collect($data['travel_validations'] ?? [])->map(fn (array $row) => $this->row($row, $fields))->all();
        } else {
            $title = 'Overall Internship Summary Report';
            $headers = ['Program', 'Students', 'Deployed', 'Pending', 'Completed', 'Attendance %', 'Journal Compliance %', 'Requirements %', 'OJT Progress %', 'Active HTEs', 'Students Requiring Intervention'];
            $rows = collect($data['analytics']['program_performance'] ?? [])->map(fn (array $row) => [
                $row['program'] ?? '', $row['students'] ?? 0, $row['deployed'] ?? 0, $row['pending'] ?? 0,
                $row['completed'] ?? 0, $row['attendance_rate'] ?? 0, $row['journal_compliance'] ?? 0,
                $row['requirements_rate'] ?? 0, $row['progress'] ?? 0, $row['active_htes'] ?? 0,
                $row['students_requiring_intervention'] ?? 0,
            ])->all();
        }

        return $this->dataset(
            $title,
            'VPAA consolidated monitoring and decision-support report',
            $this->vpaaScope($data),
            $data['filters']['date_from'] ?? null,
            $data['filters']['date_to'] ?? null,
            [
                'Total internship students' => $overview['total_students'] ?? 0,
                'Currently deployed' => $overview['deployed'] ?? 0,
                'Active internships' => $overview['active'] ?? 0,
                'Completed internships' => $overview['completed'] ?? 0,
                'Partner HTEs' => $overview['partner_htes'] ?? 0,
                'Overall compliance' => ($compliance['overall_score'] ?? 0).'%',
                'Deployment requests awaiting validation' => $data['deployment_approvals']['pending'] ?? 0,
                'Travel reports awaiting validation' => $data['travel']['reports_awaiting_validation'] ?? 0,
            ],
            $headers,
            $rows,
        );
    }

    public function download(array $dataset, string $format): BinaryFileResponse|Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $filename = Str::slug($dataset['title']).'-'.now()->format('Y-m-d-His').'.'.$format;

        return match ($format) {
            'csv' => $this->csv($dataset, $filename),
            'xlsx' => $this->xlsx($dataset, $filename),
            'pdf' => $this->pdf($dataset, $filename),
        };
    }

    private function csv(array $dataset, string $filename): BinaryFileResponse
    {
        $path = $this->temporaryPath('csv');
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [$dataset['title']]);
        fputcsv($handle, ['Scope', $dataset['scope']]);
        fputcsv($handle, ['Period', $dataset['period']]);
        fputcsv($handle, ['Generated', $dataset['generated_at']]);
        fputcsv($handle, ['Prepared By', $dataset['generated_by'] ?? 'Authorized AIMS user']);
        fputcsv($handle, []);
        fputcsv($handle, ['Summary']);
        foreach ($dataset['summary'] as $label => $value) {
            fputcsv($handle, [$label, $value]);
        }
        fputcsv($handle, []);
        fputcsv($handle, $dataset['headers']);
        foreach ($dataset['rows'] as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return response()->download($path, $filename, ['Content-Type' => 'text/csv; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    private function xlsx(array $dataset, string $filename): BinaryFileResponse
    {
        $path = $this->temporaryPath('xlsx');
        $writer = new Writer();
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(Str::limit($dataset['sheet_name'], 31, ''));

        $titleStyle = (new Style())->setFontBold()->setFontSize(16)->setFontColor('FFFFFF')->setBackgroundColor('690016');
        $sectionStyle = (new Style())->setFontBold()->setFontColor('690016')->setBackgroundColor('F7E8EC');
        $headerStyle = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('7D001D');
        $metadataStyle = (new Style())->setFontColor('475569');

        $writer->addRow(Row::fromValues([$dataset['title']], $titleStyle));
        $writer->addRow(Row::fromValues([$dataset['subtitle']], $metadataStyle));
        $writer->addRow(Row::fromValues(['Scope', $dataset['scope']], $metadataStyle));
        $writer->addRow(Row::fromValues(['Period', $dataset['period']], $metadataStyle));
        $writer->addRow(Row::fromValues(['Generated', $dataset['generated_at']], $metadataStyle));
        $writer->addRow(Row::fromValues(['Prepared By', $dataset['generated_by'] ?? 'Authorized AIMS user'], $metadataStyle));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['REPORT SUMMARY'], $sectionStyle));
        foreach ($dataset['summary'] as $label => $value) {
            $writer->addRow(Row::fromValues([$label, $value]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($dataset['headers'], $headerStyle));
        foreach ($dataset['rows'] as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        foreach ($this->columnWidths($dataset) as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }
        $writer->close();

        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    private function pdf(array $dataset, string $filename): Response
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $landscape = count($dataset['headers']) > 7;
        $dompdf->loadHtml(view('reports.export', ['report' => $dataset, 'landscape' => $landscape])->render(), 'UTF-8');
        $dompdf->setPaper('a4', $landscape ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function dataset(string $title, string $subtitle, string $scope, ?string $from, ?string $to, array $summary, array $headers, array $rows): array
    {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'scope' => $scope,
            'period' => $from && $to ? $from.' to '.$to : 'All available records',
            'generated_at' => now()->timezone('Asia/Manila')->format('F j, Y g:i A'),
            'summary' => $summary,
            'headers' => $headers,
            'rows' => $rows,
            'sheet_name' => Str::limit($title, 31, ''),
        ];
    }

    private function row(array $record, array $fields): array
    {
        return collect($fields)->map(function (string $field) use ($record) {
            $value = Arr::get($record, $field);
            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }
            if (is_array($value)) {
                return implode(', ', $value);
            }

            return $value ?? '';
        })->all();
    }

    private function coordinatorDeploymentGroups(array $students): \Illuminate\Support\Collection
    {
        return collect($students)
            ->filter(fn (array $student) => ($student['hte'] ?? 'Not assigned') !== 'Not assigned')
            ->groupBy(fn (array $student) => implode('|', [
                $student['hte'] ?? '',
                $student['hteAddress'] ?? '',
            ]))
            ->map(function ($group) {
                $first = $group->first();
                $unique = fn (string $field) => $group
                    ->pluck($field)
                    ->filter(fn ($value) => filled($value) && $value !== 'Not assigned')
                    ->unique()
                    ->implode(', ');

                return [
                    'hte' => $first['hte'] ?? '',
                    'hteAddress' => $first['hteAddress'] ?? '',
                    'department' => $unique('department') ?: 'Not assigned',
                    'studentCount' => $group->count(),
                    'workingSchedule' => $unique('workingSchedule') ?: 'Not configured',
                    'supervisor' => $unique('supervisor') ?: 'Not assigned',
                ];
            })
            ->values();
    }

    private function vpaaScope(array $data): string
    {
        $filters = $data['filters'] ?? [];
        $program = collect($data['scope']['programs'] ?? [])->firstWhere('id', $filters['program_id'] ?? null);
        $college = collect($data['scope']['colleges'] ?? [])->firstWhere('id', $filters['college_id'] ?? null);
        $hte = collect($data['scope']['htes'] ?? [])->firstWhere('id', $filters['hte_id'] ?? null);

        return collect([
            $college ? 'College: '.$college['code'] : null,
            $program ? 'Program: '.$program['code'] : null,
            $hte ? 'HTE: '.$hte['name'] : null,
            isset($filters['academic_year']) ? 'AY: '.$filters['academic_year'] : null,
            isset($filters['semester']) ? 'Semester: '.$filters['semester'] : null,
            isset($filters['internship_status']) ? 'Status: '.Str::headline($filters['internship_status']) : null,
        ])->filter()->implode(' | ') ?: 'All authorized university records';
    }

    private function temporaryPath(string $extension): string
    {
        $directory = storage_path('app/private/report-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.'.$extension;
    }

    private function columnWidths(array $dataset): array
    {
        $widths = [];
        foreach ($dataset['headers'] as $column => $header) {
            $longest = mb_strlen((string) $header);
            foreach (array_slice($dataset['rows'], 0, 250) as $row) {
                $longest = max($longest, mb_strlen((string) ($row[$column] ?? '')));
            }
            $widths[$column] = min(max($longest + 2, 11), 38);
        }

        return $widths;
    }
}
