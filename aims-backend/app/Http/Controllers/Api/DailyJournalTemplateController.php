<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ReportTemplate;
use App\Services\ReportTemplateService;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DailyJournalTemplateController extends Controller
{
    public function __construct(private readonly ReportTemplateService $templates) {}

    public function preview(Request $request, Attendance $attendance)
    {
        ProgramAccess::authorizeAttendance($request->user(), $attendance);
        $attendance->loadMissing(['student.program']);
        $template = $this->activeOfficialTemplate((int) $attendance->student->program_id);
        abort_unless($template, 404, 'No official DOCX template has been uploaded in Report Management for this program.');
        abort_unless($template->file_path && Storage::disk('local')->exists($template->file_path), 404, 'The active official template file is no longer available.');

        $student = $attendance->student;
        $values = [
            'student_name' => trim($student->first_name.' '.($student->middle_name ? $student->middle_name.' ' : '').$student->last_name),
            'student_id' => $student->student_id,
            'program' => $student->program?->code ?? '',
            'date' => $attendance->date?->format('F j, Y') ?? '—',
            'work_mode' => strtoupper((string) $attendance->work_mode),
            'session_type' => Str::headline((string) $attendance->session_type),
            'am_time_in' => $attendance->am_time_in?->format('g:i A') ?? '—',
            'am_time_out' => $attendance->am_time_out?->format('g:i A') ?? '—',
            'pm_time_in' => $attendance->pm_time_in?->format('g:i A') ?? '—',
            'pm_time_out' => $attendance->pm_time_out?->format('g:i A') ?? '—',
            'am_accomplishments' => $attendance->am_activity ?: 'No AM accomplishment submitted.',
            'pm_accomplishments' => $attendance->pm_activity ?: 'No PM accomplishment submitted.',
            'total_hours' => number_format(AttendanceHours::total($attendance), 2).' hours',
        ];

        return response()->json([
            'template' => $this->resource($template->loadMissing('uploader:id,name')),
            'html' => $this->templates->renderDailyJournalHtml(Storage::disk('local')->path($template->file_path), $values),
        ]);
    }

    private function activeOfficialTemplate(int $programId): ?ReportTemplate
    {
        return ReportTemplate::query()
            ->where('report_type', ReportTemplateService::SHARED_REPORT_TYPE)
            ->where('period_scope', ReportTemplate::SCOPE_ALL_PERIODS)
            ->whereNull('academic_period_id')
            ->where('is_active', true)
            ->where('validation_status', 'valid')
            ->where(fn ($query) => $query->where('program_id', $programId)->orWhereNull('program_id'))
            ->orderByRaw('CASE WHEN program_id IS NULL THEN 1 ELSE 0 END')
            ->latest('activated_at')
            ->first();
    }

    private function resource(ReportTemplate $template): array
    {
        return [
            'id' => $template->id,
            'file_name' => $template->original_filename,
            'file_size' => $template->file_size,
            'uploaded_by' => $template->uploader?->name,
            'uploaded_at' => $template->created_at?->toIso8601String(),
        ];
    }
}
