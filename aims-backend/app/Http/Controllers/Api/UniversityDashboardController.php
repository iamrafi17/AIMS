<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\College;
use App\Models\Evaluation;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Support\AttendanceHours;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UniversityDashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->where(
                fn ($query) => $query->when(isset($request->college_id), fn ($programs) => $programs->where('college_id', $request->college_id))
            )],
            'hte_id' => ['nullable', 'integer', 'exists:htes,id'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:30'],
            'internship_status' => ['nullable', Rule::in(['pending', 'active', 'completed', 'dropped'])],
        ]);
        foreach (['college_id', 'program_id', 'hte_id'] as $integerFilter) {
            if (isset($filters[$integerFilter])) {
                $filters[$integerFilter] = (int) $filters[$integerFilter];
            }
        }
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null;
        $trendTo = $to ?? now()->endOfDay();

        $students = Student::query()
            ->with(['program.college', 'hte', 'attendance', 'requirements'])
            ->when($filters['college_id'] ?? null, fn ($query, $value) => $query->where('college_id', $value))
            ->when($filters['program_id'] ?? null, fn ($query, $value) => $query->where('program_id', $value))
            ->when($filters['hte_id'] ?? null, fn ($query, $value) => $query->where('hte_id', $value))
            ->when($filters['academic_year'] ?? null, fn ($query, $value) => $query->where('internship_year', $value))
            ->when($filters['semester'] ?? null, fn ($query, $value) => $query->where('internship_semester', $value))
            ->when($filters['internship_status'] ?? null, fn ($query, $value) => $query->where('internship_status', $value))
            ->get();
        $studentIds = $students->pluck('id');
        $monthAttendance = Attendance::whereIn('student_id', $studentIds)
            ->when($from, fn ($query, Carbon $date) => $query->whereDate('date', '>=', $date->toDateString()))
            ->when($to, fn ($query, Carbon $date) => $query->whereDate('date', '<=', $date->toDateString()))
            ->get();
        $recentAttendance = Attendance::whereIn('student_id', $studentIds)
            ->whereDate('date', '>=', $trendTo->copy()->subDays(6)->toDateString())
            ->whereDate('date', '<=', $trendTo->toDateString())
            ->get();
        $requirements = InternshipRequirement::activeDefinitionOrLegacy()->whereIn('student_id', $studentIds)->get();
        $evaluations = Evaluation::whereIn('student_id', $studentIds)->get();
        $allTravel = TravelLog::query()
            ->whereHas('participants', fn ($query) => $query->whereIn('students.id', $studentIds))
            ->get();
        $monthTravel = $allTravel->filter(fn (TravelLog $travel) => $travel->start_time
            && (! $from || $travel->start_time->greaterThanOrEqualTo($from))
            && (! $to || $travel->start_time->lessThanOrEqualTo($to)))->values();

        $present = $monthAttendance->where('status', 'present')->count();
        $late = $monthAttendance->where('status', 'late')->count();
        $absent = $monthAttendance->where('status', 'absent')->count();
        $holiday = $monthAttendance->where('status', 'holiday')->count();
        $workdays = $present + $late + $absent;
        $attendanceRate = $workdays > 0 ? (($present + $late) / $workdays) * 100 : 0;
        $verifiableAttendance = $monthAttendance->where('status', '!=', 'holiday');
        $attendanceVerificationRate = $verifiableAttendance->count() > 0
            ? ($verifiableAttendance->where('is_verified', true)->count() / $verifiableAttendance->count()) * 100
            : 0;

        $approvedRequirements = $requirements->where('status', 'approved')->count();
        $requirementCompletionRate = $requirements->count() > 0
            ? ($approvedRequirements / $requirements->count()) * 100
            : 0;

        $deployedStudents = $students->whereNotNull('hte_id');
        $validMoaHteIds = MOA::whereIn('hte_id', $deployedStudents->pluck('hte_id')->filter())
            ->where('status', 'approved')
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->whereDate('expiration_date', '>=', now()->toDateString())
            ->pluck('hte_id')
            ->unique();
        $studentsWithValidMoa = $deployedStudents->whereIn('hte_id', $validMoaHteIds)->count();
        $moaCoverageRate = $deployedStudents->count() > 0
            ? ($studentsWithValidMoa / $deployedStudents->count()) * 100
            : 0;

        $travelCheckpoints = TravelCheckpoint::whereIn('travel_log_id', $allTravel->pluck('id'))->get();
        $travelVerificationRate = $travelCheckpoints->count() > 0
            ? ($travelCheckpoints->where('is_verified', true)->count() / $travelCheckpoints->count()) * 100
            : 0;
        $overallCompliance = collect([
            $requirementCompletionRate,
            $attendanceVerificationRate,
            $moaCoverageRate,
            $travelVerificationRate,
        ])->avg();

        $activeStudents = $students->where('internship_status', 'active');
        $averageProgress = $activeStudents->avg(function (Student $student) {
            $required = max((float) $student->required_ojt_hours, 0);
            $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

            return $required > 0 ? min(($rendered / $required) * 100, 100) : 0;
        }) ?? 0;

        $reviewedEvaluations = $evaluations->whereIn('status', ['submitted', 'finalized']);
        $averageRating = $reviewedEvaluations->avg(
            fn (Evaluation $evaluation) => (float) $evaluation->average_rating
        ) ?? 0;

        $programPerformance = $students
            ->groupBy(fn (Student $student) => $student->program?->code ?? 'Unassigned')
            ->map(function ($programStudents, string $program) use ($reviewedEvaluations, $monthAttendance) {
                $studentIds = $programStudents->pluck('id');
                $attendance = $monthAttendance->whereIn('student_id', $studentIds);
                $attended = $attendance->whereIn('status', ['present', 'late'])->count();
                $workdays = $attended + $attendance->where('status', 'absent')->count();
                $programEvaluations = $reviewedEvaluations->whereIn('student_id', $studentIds);
                $requirements = $programStudents->flatMap->requirements;
                $journalRecords = $attendance->whereIn('status', ['present', 'late']);
                $expectedJournals = $journalRecords->sum(fn (Attendance $record) => $record->session_type === 'full_day' ? 2 : 1);
                $submittedJournals = $journalRecords->sum(fn (Attendance $record) => (filled($record->am_activity) ? 1 : 0) + (filled($record->pm_activity) ? 1 : 0));
                $progress = $programStudents->avg(function (Student $student) {
                    $required = max((float) $student->required_ojt_hours, 0);
                    $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

                    return $required > 0 ? min(($rendered / $required) * 100, 100) : 0;
                }) ?? 0;

                return [
                    'program' => $program,
                    'students' => $programStudents->count(),
                    'active' => $programStudents->where('internship_status', 'active')->count(),
                    'deployed' => $programStudents->whereNotNull('hte_id')->count(),
                    'pending' => $programStudents->where('internship_status', 'pending')->count(),
                    'completed' => $programStudents->where('internship_status', 'completed')->count(),
                    'active_htes' => $programStudents->pluck('hte_id')->filter()->unique()->count(),
                    'progress' => round($progress, 1),
                    'attendance_rate' => $workdays > 0 ? round(($attended / $workdays) * 100, 1) : 0,
                    'journal_compliance' => $expectedJournals > 0 ? round(($submittedJournals / $expectedJournals) * 100, 1) : 0,
                    'requirements_rate' => $requirements->count() > 0 ? round(($requirements->where('status', 'approved')->count() / $requirements->count()) * 100, 1) : 0,
                    'students_requiring_intervention' => $programStudents->filter(function (Student $student) {
                        $required = max((float) $student->required_ojt_hours, 0);
                        $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

                        return $student->internship_status === 'dropped'
                            || ($student->internship_status === 'active' && $required > 0 && ($rendered / $required) < 0.5);
                    })->count(),
                    'average_rating' => round(
                        $programEvaluations->avg(fn (Evaluation $evaluation) => (float) $evaluation->average_rating) ?? 0,
                        1
                    ),
                ];
            })
            ->sortByDesc('students')
            ->values();

        $attendanceTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($recentAttendance, $trendTo) {
            $date = $trendTo->copy()->subDays($daysAgo);
            $records = $recentAttendance->filter(fn (Attendance $record) => $record->date->isSameDay($date));
            $attended = $records->whereIn('status', ['present', 'late'])->count();
            $workdays = $attended + $records->where('status', 'absent')->count();

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'rate' => $workdays > 0 ? round(($attended / $workdays) * 100, 1) : 0,
                'records' => $records->count(),
            ];
        })->values();

        $pendingRequirements = $requirements
            ->where('status', 'pending')
            ->whereNotNull('file_path')
            ->count();
        $pendingAttendance = $verifiableAttendance->where('is_verified', false)->count();
        $pendingEvaluations = $evaluations->where('status', 'draft')->count();
        $pendingRegistrations = $students->where('registration_status', 'pending')->count();
        $pendingCheckpoints = $travelCheckpoints->where('is_verified', false)->count();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'filters' => array_merge($filters, [
                'date_from' => $from?->toDateString(),
                'date_to' => $to?->toDateString(),
            ]),
            'scope' => [
                'colleges' => College::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
                'programs' => Program::query()->where('is_active', true)->orderBy('code')->get(['id', 'college_id', 'code', 'name']),
                'htes' => HTE::query()->where('is_active', true)->orderBy('name')->get(['id', 'college_id', 'program_id', 'name']),
                'academic_years' => Student::query()->whereNotNull('internship_year')->distinct()->orderByDesc('internship_year')->pluck('internship_year')->values(),
                'semesters' => Student::query()->whereNotNull('internship_semester')->distinct()->orderBy('internship_semester')->pluck('internship_semester')->values(),
            ],
            'overview' => [
                'total_students' => $students->count(),
                'active' => $activeStudents->count(),
                'completed' => $students->where('internship_status', 'completed')->count(),
                'pending' => $students->where('internship_status', 'pending')->count(),
                'deployed' => $deployedStudents->count(),
                'partner_htes' => $deployedStudents->pluck('hte_id')->unique()->count(),
                'average_progress' => round($averageProgress, 1),
            ],
            'analytics' => [
                'average_progress' => round($averageProgress, 1),
                'average_rating' => round($averageRating, 1),
                'evaluations_completed' => $reviewedEvaluations->count(),
                'internship_status' => [
                    ['label' => 'Active', 'value' => $activeStudents->count(), 'color' => '#059669'],
                    ['label' => 'Completed', 'value' => $students->where('internship_status', 'completed')->count(), 'color' => '#2563eb'],
                    ['label' => 'Pending', 'value' => $students->where('internship_status', 'pending')->count(), 'color' => '#d97706'],
                    ['label' => 'Dropped', 'value' => $students->where('internship_status', 'dropped')->count(), 'color' => '#dc2626'],
                ],
                'program_performance' => $programPerformance,
            ],
            'attendance' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'holiday' => $holiday,
                'total' => $monthAttendance->count(),
                'rate' => round($attendanceRate, 1),
                'verified' => $monthAttendance->where('is_verified', true)->count(),
                'verification_rate' => round($attendanceVerificationRate, 1),
                'total_hours' => round($monthAttendance->sum(fn (Attendance $record) => $this->renderedHours($record)), 1),
                'trend' => $attendanceTrend,
            ],
            'compliance' => [
                'overall_score' => round($overallCompliance, 1),
                'requirements_rate' => round($requirementCompletionRate, 1),
                'requirements_approved' => $approvedRequirements,
                'requirements_total' => $requirements->count(),
                'attendance_verification_rate' => round($attendanceVerificationRate, 1),
                'moa_coverage_rate' => round($moaCoverageRate, 1),
                'valid_moa_students' => $studentsWithValidMoa,
                'travel_verification_rate' => round($travelVerificationRate, 1),
            ],
            'travel' => [
                'active' => $allTravel->whereIn('status', ['active', 'traveling', 'arrived'])->count(),
                'total_this_month' => $monthTravel->count(),
                'completed_this_month' => $monthTravel->where('status', 'completed')->count(),
                'cancelled_this_month' => $monthTravel->where('status', 'cancelled')->count(),
                'completion_rate' => $monthTravel->count() > 0
                    ? round(($monthTravel->where('status', 'completed')->count() / $monthTravel->count()) * 100, 1)
                    : 0,
                'verified_checkpoints' => $travelCheckpoints->where('is_verified', true)->count(),
                'checkpoint_total' => $travelCheckpoints->count(),
            ],
            'pending_approvals' => [
                'evaluations' => $pendingEvaluations,
                'attendance' => $pendingAttendance,
                'requirements' => $pendingRequirements,
                'registrations' => $pendingRegistrations,
                'travel_checkpoints' => $pendingCheckpoints,
                'total' => $pendingEvaluations + $pendingAttendance + $pendingRequirements
                    + $pendingRegistrations + $pendingCheckpoints,
            ],
        ]);
    }

    private function renderedHours(Attendance $attendance): float
    {
        return AttendanceHours::total($attendance);
    }
}
