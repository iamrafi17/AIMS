<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\HTE;
use App\Models\Student;
use App\Models\TravelLog;
use App\Support\AttendanceHours;
use App\Support\ProgramAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoordinatorReportController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'academic_section_id' => ['nullable', 'integer', Rule::exists('academic_sections', 'id')->where(fn ($query) => $query
                ->where('program_id', $programId)
                ->where('is_active', true))],
        ]);
        $sectionId = isset($filters['academic_section_id']) ? (int) $filters['academic_section_id'] : null;
        $program = $request->user()->program()->first(['programs.id', 'programs.name', 'programs.code']);
        $sections = AcademicSection::where('program_id', $programId)
            ->where('is_active', true)
            ->orderByDesc('academic_year')
            ->orderBy('year_level')
            ->orderBy('name')
            ->get(['id', 'program_id', 'name', 'year_level', 'academic_year']);
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();

        $students = Student::query()
            ->where('program_id', $programId)
            ->when($sectionId, fn ($query) => $query->where('academic_section_id', $sectionId))
            ->with(['program:id,code', 'hte:id,name', 'requirements'])
            ->orderBy('last_name')
            ->get();

        $attendance = Attendance::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->whereHas('student', fn ($query) => $query
                ->where('program_id', $programId)
                ->when($sectionId, fn ($studentQuery) => $studentQuery->where('academic_section_id', $sectionId)))
            ->with(['student:id,student_id,first_name,last_name,program_id,academic_section_id,section,hte_id', 'student.program:id,code', 'student.hte:id,name'])
            ->latest('date')
            ->get();
        $attendanceByStudent = $attendance->groupBy('student_id');

        $attendanceRows = $attendance->map(fn (Attendance $record) => [
            'id' => $record->id,
            'date' => $record->date?->toDateString(),
            'student' => trim($record->student?->first_name.' '.$record->student?->last_name),
            'studentId' => $record->student?->student_id,
            'program' => $record->student?->program?->code,
            'section' => $record->student?->section,
            'hte' => $record->student?->hte?->name ?? 'Not assigned',
            'mode' => strtoupper($record->work_mode ?: 'wfo'),
            'timeIn' => $record->am_time_in?->format('g:i A') ?? $record->time_in?->format('g:i A') ?? '—',
            'timeOut' => $record->pm_time_out?->format('g:i A') ?? $record->time_out?->format('g:i A') ?? '—',
            'hours' => round($this->renderedHours($record), 2),
            'status' => $record->status,
            'verified' => $record->is_verified,
        ])->values();

        $studentRows = $students->map(function (Student $student) use ($attendanceByStudent) {
            $records = $attendanceByStudent->get($student->id, collect());
            $requirements = $student->requirements;
            $workdays = $records->whereIn('status', ['present', 'late', 'absent'])->count();
            $present = $records->whereIn('status', ['present', 'late'])->count();
            $expected = $records->whereIn('status', ['present', 'late'])->sum(fn (Attendance $record) => $record->session_type === 'full_day' ? 2 : 1);
            $submitted = $records->whereIn('status', ['present', 'late'])->sum(fn (Attendance $record) => (filled($record->am_activity) ? 1 : 0) + (filled($record->pm_activity) ? 1 : 0));

            return [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'studentId' => $student->student_id,
                'program' => $student->program?->code,
                'section' => $student->section,
                'hte' => $student->hte?->name ?? 'Not assigned',
                'rendered' => round($records->sum(fn (Attendance $record) => $this->renderedHours($record)), 2),
                'required' => (float) $student->required_ojt_hours,
                'attendance' => $workdays ? round(($present / $workdays) * 100) : 0,
                'requirements' => $requirements->count() ? round(($requirements->where('status', 'approved')->count() / $requirements->count()) * 100) : 0,
                'journals' => $expected ? round(($submitted / $expected) * 100) : 0,
                'status' => $student->internship_status,
            ];
        })->values();

        $journalRows = $students->map(function (Student $student) use ($attendanceByStudent) {
            $records = $attendanceByStudent->get($student->id, collect())->whereIn('status', ['present', 'late']);
            $expected = $records->sum(fn (Attendance $record) => $record->session_type === 'full_day' ? 2 : 1);
            $submitted = $records->sum(fn (Attendance $record) => (filled($record->am_activity) ? 1 : 0) + (filled($record->pm_activity) ? 1 : 0));
            $reviewed = $records->sum(fn (Attendance $record) => $record->journal_status === 'reviewed' ? (filled($record->am_activity) ? 1 : 0) + (filled($record->pm_activity) ? 1 : 0) : 0);
            $pending = max($submitted - $reviewed, 0);
            $missing = max($expected - $submitted, 0);

            return [
                'id' => $student->id, 'student' => trim($student->first_name.' '.$student->last_name),
                'studentId' => $student->student_id, 'program' => $student->program?->code,
                'section' => $student->section,
                'submitted' => $submitted, 'expected' => $expected, 'approved' => $reviewed,
                'pending' => $pending, 'missing' => $missing,
                'lastSubmission' => optional($records->filter(fn (Attendance $record) => filled($record->am_activity) || filled($record->pm_activity))->sortByDesc('date')->first()?->date)->format('M j, Y'),
                'status' => $missing > 0 ? 'attention' : ($pending > 0 ? 'on-track' : 'complete'),
            ];
        })->values();

        $deploymentRows = HTE::query()
            ->where('program_id', $programId)
            ->when($sectionId, fn ($query) => $query->whereHas('students', fn ($studentQuery) => $studentQuery->where('academic_section_id', $sectionId)))
            ->with([
                'students' => fn ($query) => $query
                    ->where('students.program_id', $programId)
                    ->when($sectionId, fn ($studentQuery) => $studentQuery->where('academic_section_id', $sectionId))
                    ->with('program:id,code'),
                'moas' => fn ($query) => $query->where('program_id', $programId),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (HTE $hte) {
                $students = $hte->students;
                $latestMoa = $hte->moas->sortByDesc('expiration_date')->first();
                $moa = ! $latestMoa ? 'Pending' : ($latestMoa->expiration_date?->isPast() ? 'Expiring' : ucfirst($latestMoa->status));

                return ['id' => $hte->id, 'hte' => $hte->name, 'sector' => 'Partner HTE', 'location' => $hte->address,
                    'deployed' => $students->count(),
                    'active' => $students->where('internship_status', 'active')->count(), 'completed' => $students->where('internship_status', 'completed')->count(),
                    'programs' => $students->pluck('program.code')->filter()->unique()->values(),
                    'moa' => $moa, 'moaExpiry' => $latestMoa?->expiration_date?->format('M j, Y') ?? 'Not set'];
            })->values();

        $travelRows = TravelLog::query()
            ->whereBetween('start_time', [$from, $to])
            ->whereHas('participants', fn ($query) => $query
                ->where('students.program_id', $programId)
                ->when($sectionId, fn ($studentQuery) => $studentQuery->where('academic_section_id', $sectionId)))
            ->with([
                'participants' => fn ($query) => $query
                    ->where('students.program_id', $programId)
                    ->when($sectionId, fn ($studentQuery) => $studentQuery->where('academic_section_id', $sectionId))
                    ->select(['students.id', 'students.student_id', 'students.first_name', 'students.last_name', 'students.program_id', 'students.academic_section_id', 'students.section'])
                    ->with('program:id,code'),
                'checkpoints:id,travel_log_id',
            ])
            ->latest('start_time')
            ->get()
            ->map(function (TravelLog $travel) {
                $student = $travel->participants->first();

                return ['id' => $travel->session_code, 'student' => trim($student?->first_name.' '.$student?->last_name), 'studentId' => $student?->student_id,
                    'program' => $student?->program?->code, 'section' => $student?->section, 'destination' => $travel->destination, 'date' => $travel->start_time?->toDateString(),
                    'checkpoints' => $travel->checkpoints->count().'/'.$travel->checkpoints->count(),
                    'duration' => $travel->start_time && $travel->end_time ? $travel->start_time->diffForHumans($travel->end_time, true) : '—',
                    'status' => $travel->status, 'compliance' => $travel->status === 'completed' ? 100 : 0];
            })->values();

        return response()->json([
            'program' => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
            ],
            'sections' => $sections,
            'selected_section' => $sectionId ? $sections->firstWhere('id', $sectionId) : null,
            'attendance' => $attendanceRows,
            'journals' => $journalRows,
            'students' => $studentRows,
            'deployments' => $deploymentRows,
            'travel' => $travelRows,
        ]);
    }

    private function renderedHours(Attendance $record): float
    {
        return AttendanceHours::total($record);
    }
}
