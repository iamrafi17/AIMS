<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\ApprovalRecord;
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
            'student_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (! ProgramAccess::studentQuery($request->user())->whereKey($value)->exists()) {
                    $fail('The selected student does not belong to your program.');
                }
            }],
            'hte_id' => ['nullable', 'integer', Rule::exists('hte_programs', 'hte_id')->where(fn ($query) => $query->where('program_id', $programId)->where('is_active', true))],
            'attendance_status' => ['nullable', Rule::in(['present', 'late', 'absent', 'holiday'])],
            'work_mode' => ['nullable', Rule::in(['wfo', 'wfh', 'field'])],
            'internship_status' => ['nullable', Rule::in(['pending', 'active', 'completed', 'dropped'])],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:30'],
            'academic_section_id' => ['nullable', 'integer', Rule::exists('academic_sections', 'id')->where(fn ($query) => $query
                ->where('program_id', $programId)
                ->whereIn('id', ProgramAccess::sectionIds($request->user()))
                ->where('is_active', true))],
        ]);
        $sectionId = isset($filters['academic_section_id']) ? (int) $filters['academic_section_id'] : null;
        $program = $request->user()->program()->first(['programs.id', 'programs.name', 'programs.code']);
        $sections = ProgramAccess::sectionQuery($request->user())
            ->where('is_active', true)
            ->newestPeriodFirst()
            ->orderBy('year_level')
            ->orderBy('name')
            ->get(['id', 'program_id', 'academic_period_id', 'name', 'year_level']);
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null;

        $students = ProgramAccess::studentQuery($request->user())
            ->when($sectionId, fn ($query) => $query->forAcademicSection($sectionId))
            ->when($filters['student_id'] ?? null, fn ($query, $value) => $query->whereKey($value))
            ->when($filters['hte_id'] ?? null, fn ($query, $value) => $query->forHte($value))
            ->when($filters['internship_status'] ?? null, fn ($query, $value) => $query->forInternshipStatus($value))
            ->when($filters['academic_year'] ?? null, fn ($query, $value) => $query->forAcademicYear($value))
            ->when($filters['semester'] ?? null, fn ($query, $value) => $query->forSemester($value))
            ->with([
                'program:id,code',
                'academicSection:id,academic_period_id,name,year_level',
                'academicSection.academicPeriod:id,academic_year,semester',
                'hte:id,name,address,latitude,longitude,geofence_radius,geofence_enabled',
                'hteDepartment:id,hte_id,department_name',
                'requirements.reviewer.roleDefinition:id,code',
                'requirements.reviewer.employeeProfile:id,user_id,first_name,middle_name,last_name',
                'supervisor.roleDefinition:id,code',
                'supervisor.normalizedSupervisorProfile:id,user_id,full_name',
                'currentInternship.currentPlacement.supervisor.supervisorProfile:id,full_name,user_id',
            ])
            ->orderBy('last_name')
            ->get();
        $studentIds = $students->pluck('id');

        $attendance = Attendance::query()
            ->forStudents($studentIds)
            ->when($from, fn ($query, Carbon $date) => $query->whereDate('date', '>=', $date->toDateString()))
            ->when($to, fn ($query, Carbon $date) => $query->whereDate('date', '<=', $date->toDateString()))
            ->when($filters['attendance_status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['work_mode'] ?? null, fn ($query, $value) => $query->where('work_mode', $value))
            ->with(['student', 'student.program:id,code', 'student.hte:id,name,latitude,longitude,geofence_radius,geofence_enabled'])
            ->latest('date')
            ->get();
        $attendanceByStudent = $attendance->groupBy('student_id');
        $allAttendanceByStudent = Attendance::query()->forStudents($studentIds)->get()->groupBy('student_id');
        $deploymentApprovals = ApprovalRecord::query()
            ->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
            ->whereIn('subject_id', $studentIds)
            ->with([
                'submitter.roleDefinition:id,code',
                'submitter.employeeProfile:id,user_id,first_name,middle_name,last_name',
                'decisionMaker.roleDefinition:id,code',
                'decisionMaker.employeeProfile:id,user_id,first_name,middle_name,last_name',
            ])
            ->latest('created_at')
            ->get()
            ->groupBy('subject_id');

        $attendanceRows = $attendance->map(fn (Attendance $record) => [
            'id' => $record->id,
            'date' => $record->date?->toDateString(),
            'student' => trim($record->student?->first_name.' '.$record->student?->last_name),
            'studentId' => $record->student?->student_id,
            'program' => $record->student?->program?->code,
            'section' => $record->student?->section,
            'hte' => $record->student?->hte?->name ?? 'Not assigned',
            'mode' => strtoupper($record->work_mode ?: 'wfo'),
            'sessionType' => $record->session_type,
            'amTimeIn' => $record->am_time_in?->format('g:i A'),
            'amTimeOut' => $record->am_time_out?->format('g:i A'),
            'pmTimeIn' => $record->pm_time_in?->format('g:i A'),
            'pmTimeOut' => $record->pm_time_out?->format('g:i A'),
            'timeIn' => $record->am_time_in?->format('g:i A') ?? $record->time_in?->format('g:i A') ?? '—',
            'timeOut' => $record->pm_time_out?->format('g:i A') ?? $record->time_out?->format('g:i A') ?? '—',
            'hours' => round($this->renderedHours($record), 2),
            'overtimeHours' => (float) $record->overtime_hours,
            'status' => $record->status,
            'verified' => $record->is_verified,
            'gps' => $record->latitude_in && $record->longitude_in ? $record->latitude_in.', '.$record->longitude_in : null,
            'geofenceStatus' => $this->geofenceStatus($record),
            'remarks' => $record->supervisor_feedback ?: $record->journal_feedback,
        ])->values();

        $studentRows = $students->map(function (Student $student) use ($attendanceByStudent, $allAttendanceByStudent, $deploymentApprovals) {
            $records = $attendanceByStudent->get($student->id, collect());
            $allRecords = $allAttendanceByStudent->get($student->id, collect());
            $requirements = $student->requirements;
            $workdays = $records->whereIn('status', ['present', 'late', 'absent'])->count();
            $present = $records->whereIn('status', ['present', 'late'])->count();
            $expected = $records->whereIn('status', ['present', 'late'])->sum(fn (Attendance $record) => $record->session_type === 'full_day' ? 2 : 1);
            $submitted = $records->whereIn('status', ['present', 'late'])->sum(fn (Attendance $record) => (filled($record->am_activity) ? 1 : 0) + (filled($record->pm_activity) ? 1 : 0));

            $rendered = $allRecords->sum(fn (Attendance $record) => $this->renderedHours($record));
            $required = (float) $student->required_ojt_hours;
            $progress = $required > 0 ? min(($rendered / $required) * 100, 100) : 0;
            $displayStatus = $student->internship_status === 'active' && $progress >= 80 && $progress < 100
                ? 'near_completion'
                : $student->internship_status;
            $approvalHistory = $deploymentApprovals->get($student->id, collect());
            $programHeadReview = $approvalHistory->first(fn (ApprovalRecord $approval) => $approval->submitter?->role === 'coordinator');
            $vpaaValidation = $approvalHistory->first(fn (ApprovalRecord $approval) => $approval->submitter?->role === 'program_head');

            return [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'studentId' => $student->student_id,
                'program' => $student->program?->code,
                'yearLevel' => $student->year_level,
                'section' => $student->section,
                'deploymentBatch' => $student->academicSection?->name ?? $student->section ?? 'Not assigned',
                'hte' => $student->hte?->name ?? 'Not assigned',
                'hteAddress' => $student->hte?->address,
                'department' => $student->hteDepartment?->department_name ?? 'Not assigned',
                'supervisor' => $student->currentInternship?->currentPlacement?->supervisor?->full_name
                    ?? $student->supervisor?->name ?? 'Not assigned',
                'deploymentDate' => $student->ojt_start_date?->toDateString(),
                'academicYear' => $student->internship_year,
                'semester' => $student->internship_semester,
                'rendered' => round($rendered, 2),
                'required' => $required,
                'remaining' => round(max($required - $rendered, 0), 2),
                'overtime' => round($records->sum(fn (Attendance $record) => (float) $record->overtime_hours), 2),
                'progress' => round($progress, 1),
                'progressStatus' => $progress >= 100 ? 'completed' : ($progress >= 80 ? 'near_completion' : ($student->internship_status === 'active' ? 'on_track' : $student->internship_status)),
                'presentDays' => $records->where('status', 'present')->count(),
                'lateRecords' => $records->where('status', 'late')->count(),
                'absences' => $records->where('status', 'absent')->count(),
                'attendance' => $workdays ? round(($present / $workdays) * 100) : 0,
                'requirementsTotal' => $requirements->count(),
                'requirementsSubmitted' => $requirements->whereNotNull('file_path')->count(),
                'requirementsApproved' => $requirements->where('status', 'approved')->count(),
                'requirementsPending' => $requirements->where('status', 'pending')->whereNotNull('file_path')->count(),
                'requirementsRejected' => $requirements->where('status', 'rejected')->count(),
                'requirementsMissing' => $requirements->whereNull('file_path')->count(),
                'requirements' => $requirements->count() ? round(($requirements->where('status', 'approved')->count() / $requirements->count()) * 100) : 0,
                'journals' => $expected ? round(($submitted / $expected) * 100) : 0,
                'readiness' => $requirements->count() > 0 && $requirements->every(fn ($requirement) => $requirement->status === 'approved') ? 'ready_for_deployment' : 'incomplete_requirements',
                'workingSchedule' => $this->studentSchedule($student),
                'programHeadReviewStatus' => $programHeadReview?->status ?? 'not_submitted',
                'vpaaValidationStatus' => $vpaaValidation?->status ?? 'not_submitted',
                'deploymentStatus' => $student->schedule_status,
                'status' => $displayStatus,
            ];
        })->values();

        $requirementRows = $students->flatMap(fn (Student $student) => $student->requirements->map(fn ($requirement) => [
            'id' => $requirement->id,
            'student' => trim($student->first_name.' '.$student->last_name),
            'studentId' => $student->student_id,
            'program' => $student->program?->code,
            'section' => $student->section,
            'requirement' => $requirement->requirement_name,
            'submittedAt' => $requirement->file_path ? $requirement->updated_at?->toIso8601String() : null,
            'status' => $requirement->file_path ? $requirement->status : 'not_submitted',
            'reviewedBy' => $requirement->reviewer?->name,
            'reviewedAt' => $requirement->reviewed_at?->toIso8601String(),
            'remarks' => $requirement->feedback,
        ]))->values();

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

        $studentsByHte = $students
            ->filter(fn (Student $student) => filled($student->hte_id))
            ->groupBy(fn (Student $student) => (int) $student->hte_id);
        $deploymentRows = HTE::query()
            ->whereHas('programs', fn ($query) => $query->whereKey($programId)->where('hte_programs.is_active', true))
            ->whereIn('id', $studentsByHte->keys())
            ->with(['moas' => fn ($query) => $query->where('program_id', $programId)])
            ->orderBy('name')
            ->get()
            ->map(function (HTE $hte) use ($studentsByHte) {
                $students = $studentsByHte->get($hte->id, collect());
                $hte->setRelation('students', $students);
                $latestMoa = $hte->moas->sortByDesc('expiration_date')->first();
                $moa = ! $latestMoa ? 'Pending' : ($latestMoa->expiration_date?->isPast() ? 'Expiring' : ucfirst($latestMoa->status));

                return ['id' => $hte->id, 'hte' => $hte->name, 'sector' => 'Partner HTE', 'location' => $hte->address,
                    'deployed' => $students->count(),
                    'active' => $students->where('internship_status', 'active')->count(), 'completed' => $students->where('internship_status', 'completed')->count(),
                    'programs' => $students->pluck('program.code')->filter()->unique()->values(),
                    'departments' => $students->pluck('hteDepartment.department_name')->filter()->unique()->values(),
                    'workingSchedules' => $students->map(fn (Student $student) => $this->studentSchedule($student))->filter()->unique()->values(),
                    'supervisors' => $students->map(fn (Student $student) => $student->currentInternship?->currentPlacement?->supervisor?->full_name
                        ?? $student->supervisor?->name)->filter()->unique()->values(),
                    'moa' => $moa, 'moaExpiry' => $latestMoa?->expiration_date?->format('M j, Y') ?? 'Not set'];
            })->values();

        $travelRows = TravelLog::query()
            ->when($from, fn ($query, Carbon $date) => $query->where('start_time', '>=', $date))
            ->when($to, fn ($query, Carbon $date) => $query->where('start_time', '<=', $date))
            ->whereHas('participants', fn ($query) => $query->whereIn('students.id', $studentIds))
            ->with([
                'participants' => fn ($query) => $query
                    ->whereIn('students.id', $studentIds)
                    ->select(['students.id', 'students.student_id', 'students.first_name', 'students.last_name'])
                    ->with('program:id,code'),
                'checkpoints:id,travel_log_id,sequence,checkpoint_name,point_type,captured_at,latitude,longitude,readable_location,photo_path,notes,is_verified',
                'hte:id,name',
                'report:id,travel_log_id,coordinator_id,report_number,remarks,status,version,generated_at,submitted_at,program_head_validated_at,forwarded_at,final_validated_at',
                'report.validations:id,travel_report_id,reviewer_id,reviewer_role,action,reason,comments,reviewed_at,report_version',
            ])
            ->latest('start_time')
            ->get()
            ->map(function (TravelLog $travel) {
                $student = $travel->participants->first();
                $programHeadReview = $travel->report?->validations->where('reviewer_role', 'program_head')->sortByDesc('reviewed_at')->first();
                $vpaaValidation = $travel->report?->validations->whereIn('reviewer_role', ['dean', 'vpaa'])->sortByDesc('reviewed_at')->first();
                $checkpointSummary = $travel->checkpoints->sortBy('sequence')->map(fn ($checkpoint) => collect([
                    $checkpoint->checkpoint_name ?: $checkpoint->readable_location ?: ucfirst($checkpoint->point_type),
                    $checkpoint->captured_at?->format('g:i A'),
                    $checkpoint->latitude && $checkpoint->longitude ? $checkpoint->latitude.', '.$checkpoint->longitude : null,
                ])->filter()->implode(' - '))->implode('; ');

                return ['id' => $travel->session_code, 'student' => trim($student?->first_name.' '.$student?->last_name), 'studentId' => $student?->student_id,
                    'program' => $student?->program?->code, 'section' => $student?->section, 'hte' => $travel->hte?->name,
                    'participants' => $travel->participants->map(fn (Student $participant) => trim($participant->first_name.' '.$participant->last_name))->values(),
                    'destination' => $travel->destination, 'date' => $travel->start_time?->toDateString(),
                    'departureLocation' => $travel->route_notes ?: $travel->hte?->name,
                    'departureTime' => $travel->departed_at?->format('M j, Y g:i A') ?? $travel->start_time?->format('M j, Y g:i A'),
                    'arrivalLocation' => $travel->destination,
                    'arrivalTime' => $travel->arrived_at?->format('M j, Y g:i A') ?? $travel->end_time?->format('M j, Y g:i A'),
                    'routeSummary' => $travel->route_notes,
                    'checkpointSummary' => $checkpointSummary ?: 'No checkpoints recorded',
                    'photoEvidence' => filled($travel->arrival_photo_path) || $travel->checkpoints->contains(fn ($checkpoint) => filled($checkpoint->photo_path)),
                    'checkpoints' => $travel->checkpoints->where('is_verified', true)->count().'/'.$travel->checkpoints->count(),
                    'duration' => $travel->start_time && $travel->end_time ? $travel->start_time->diffForHumans($travel->end_time, true) : '—',
                    'reportNumber' => $travel->report?->report_number, 'reportStatus' => $travel->report?->status ?? 'not_generated',
                    'coordinatorRemarks' => $travel->report?->remarks,
                    'submittedAt' => $travel->report?->submitted_at?->toIso8601String(),
                    'programHeadReview' => $programHeadReview?->action ?? 'not_reviewed',
                    'programHeadRemarks' => $programHeadReview?->comments ?: $programHeadReview?->reason,
                    'vpaaValidation' => $vpaaValidation?->action ?? 'not_reviewed',
                    'vpaaRemarks' => $vpaaValidation?->comments ?: $vpaaValidation?->reason,
                    'status' => $travel->status,
                    'compliance' => $travel->checkpoints->count() ? round(($travel->checkpoints->where('is_verified', true)->count() / $travel->checkpoints->count()) * 100) : ($travel->status === 'completed' ? 100 : 0)];
            })->values();

        return response()->json([
            'program' => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
            ],
            'sections' => $sections,
            'selected_section' => $sectionId ? $sections->firstWhere('id', $sectionId) : null,
            'filters' => array_merge($filters, ['date_from' => $from?->toDateString(), 'date_to' => $to?->toDateString()]),
            'attendance' => $attendanceRows,
            'journals' => $journalRows,
            'students' => $studentRows,
            'requirements' => $requirementRows,
            'deployments' => $deploymentRows,
            'travel' => $travelRows,
        ]);
    }

    private function renderedHours(Attendance $record): float
    {
        return AttendanceHours::total($record);
    }

    private function studentSchedule(Student $student): string
    {
        $days = collect($student->work_days ?? [])->map(fn (string $day) => ucfirst(substr($day, 0, 3)))->implode(', ');
        $am = $student->official_am_start && $student->official_am_end
            ? $this->scheduleTime($student->official_am_start).' - '.$this->scheduleTime($student->official_am_end)
            : null;
        $pm = $student->official_pm_start && $student->official_pm_end
            ? $this->scheduleTime($student->official_pm_start).' - '.$this->scheduleTime($student->official_pm_end)
            : null;

        return collect([$days ?: null, $am ? 'AM '.$am : null, $pm ? 'PM '.$pm : null])->filter()->implode(' | ') ?: 'Not configured';
    }

    private function scheduleTime(string $time): string
    {
        return Carbon::parse($time)->format('g:i');
    }

    private function geofenceStatus(Attendance $record): string
    {
        $hte = $record->student?->hte;
        if (! $hte?->geofence_enabled || ! $hte->latitude || ! $hte->longitude || ! $record->latitude_in || ! $record->longitude_in) {
            return 'not_available';
        }

        $earthRadius = 6371000;
        $latitudeDifference = deg2rad((float) $record->latitude_in - (float) $hte->latitude);
        $longitudeDifference = deg2rad((float) $record->longitude_in - (float) $hte->longitude);
        $a = sin($latitudeDifference / 2) ** 2
            + cos(deg2rad((float) $hte->latitude)) * cos(deg2rad((float) $record->latitude_in))
            * sin($longitudeDifference / 2) ** 2;
        $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $distance <= (int) $hte->geofence_radius ? 'within_geofence' : 'outside_geofence';
    }
}
