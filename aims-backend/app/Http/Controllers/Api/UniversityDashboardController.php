<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\Student;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use Illuminate\Http\Request;

class UniversityDashboardController extends Controller
{
    public function index(Request $request)
    {
        $students = Student::with(['program', 'hte', 'attendance', 'requirements'])->get();
        $monthAttendance = Attendance::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->get();
        $recentAttendance = Attendance::whereDate('date', '>=', now()->subDays(6)->toDateString())->get();
        $requirements = InternshipRequirement::activeDefinitionOrLegacy()->get();
        $evaluations = Evaluation::all();
        $monthTravel = TravelLog::whereMonth('start_time', now()->month)
            ->whereYear('start_time', now()->year)
            ->get();

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
        $validMoaHteIds = MOA::where('status', 'approved')
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->whereDate('expiration_date', '>=', now()->toDateString())
            ->pluck('hte_id')
            ->unique();
        $studentsWithValidMoa = $deployedStudents->whereIn('hte_id', $validMoaHteIds)->count();
        $moaCoverageRate = $deployedStudents->count() > 0
            ? ($studentsWithValidMoa / $deployedStudents->count()) * 100
            : 0;

        $travelCheckpoints = TravelCheckpoint::all();
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
            ->map(function ($programStudents, string $program) use ($reviewedEvaluations) {
                $studentIds = $programStudents->pluck('id');
                $attendance = $programStudents->flatMap->attendance;
                $attended = $attendance->whereIn('status', ['present', 'late'])->count();
                $workdays = $attended + $attendance->where('status', 'absent')->count();
                $programEvaluations = $reviewedEvaluations->whereIn('student_id', $studentIds);
                $progress = $programStudents->avg(function (Student $student) {
                    $required = max((float) $student->required_ojt_hours, 0);
                    $rendered = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));

                    return $required > 0 ? min(($rendered / $required) * 100, 100) : 0;
                }) ?? 0;

                return [
                    'program' => $program,
                    'students' => $programStudents->count(),
                    'active' => $programStudents->where('internship_status', 'active')->count(),
                    'progress' => round($progress, 1),
                    'attendance_rate' => $workdays > 0 ? round(($attended / $workdays) * 100, 1) : 0,
                    'average_rating' => round(
                        $programEvaluations->avg(fn (Evaluation $evaluation) => (float) $evaluation->average_rating) ?? 0,
                        1
                    ),
                ];
            })
            ->sortByDesc('students')
            ->values();

        $attendanceTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($recentAttendance) {
            $date = now()->subDays($daysAgo);
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
                'active' => TravelLog::where('status', 'active')->count(),
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
        $regular = collect([
            [$attendance->am_time_in, $attendance->am_time_out],
            [$attendance->pm_time_in, $attendance->pm_time_out],
        ])->sum(fn (array $slot) => $slot[0] && $slot[1] ? $slot[0]->diffInSeconds($slot[1]) / 3600 : 0);

        if ($regular <= 0 && ! $attendance->am_time_in && ! $attendance->pm_time_in && $attendance->time_in && $attendance->time_out) {
            $regular = $attendance->time_in->diffInSeconds($attendance->time_out) / 3600;
        }

        return $regular + (float) $attendance->overtime_hours;
    }
}
