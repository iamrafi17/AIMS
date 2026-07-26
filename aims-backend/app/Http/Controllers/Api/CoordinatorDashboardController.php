<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\Student;
use App\Models\TravelLog;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;

class CoordinatorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $students = Student::where('program_id', $programId)->get();
        $monthAttendance = Attendance::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->get();

        $present = $monthAttendance->where('status', 'present')->count();
        $late = $monthAttendance->where('status', 'late')->count();
        $absent = $monthAttendance->where('status', 'absent')->count();
        $holiday = $monthAttendance->where('status', 'holiday')->count();
        $workdayRecords = $present + $late + $absent;
        $attendanceRate = $workdayRecords > 0 ? (($present + $late) / $workdayRecords) * 100 : 0;

        $expectedJournals = $monthAttendance->sum(fn (Attendance $record) => in_array($record->status, ['present', 'late'], true)
            ? ($record->session_type === 'full_day' ? 2 : 1)
            : 0);
        $submittedJournals = $monthAttendance->sum(function (Attendance $record) {
            if (! in_array($record->status, ['present', 'late'], true)) {
                return 0;
            }

            return (blank($record->am_activity) ? 0 : 1) + (blank($record->pm_activity) ? 0 : 1);
        });

        $requirements = InternshipRequirement::activeDefinitionOrLegacy()
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->get();
        $approvedRequirements = $requirements->where('status', 'approved')->count();
        $requirementTotal = $requirements->count();

        $moas = MOA::with('hte')->where('program_id', $programId)->get();
        $approvedMoas = $moas->filter(fn (MOA $moa) => $moa->status === 'approved' && $moa->expiration_date->gte(now()->startOfDay()));
        $expiredMoas = $moas->filter(fn (MOA $moa) => $moa->status === 'expired' || $moa->expiration_date->lt(now()->startOfDay()));
        $expiringMoas = $approvedMoas->filter(fn (MOA $moa) => $moa->expiration_date->lte(now()->addDays(30)->endOfDay()));

        $monthTravel = TravelLog::whereMonth('start_time', now()->month)
            ->whereYear('start_time', now()->year)
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->get();
        $activeTravel = TravelLog::where('status', 'active')
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->count();

        $activeInterns = Student::with(['college', 'program', 'hte', 'attendance', 'requirements.definition'])
            ->where('internship_status', 'active')
            ->where('program_id', $programId)
            ->orderBy('last_name')
            ->limit(8)
            ->get()
            ->map(function (Student $student) {
                $hours = $student->attendance->sum(fn (Attendance $record) => $this->renderedHours($record));
                $requiredHours = max((float) $student->required_ojt_hours, 0);
                $lastAttendance = $student->attendance->sortByDesc('date')->first();
                $activeRequirements = $student->requirements->filter(
                    fn (InternshipRequirement $requirement) => ! $requirement->program_requirement_id || $requirement->definition?->is_active
                );
                $requirementCount = $activeRequirements->count();
                $approved = $activeRequirements->where('status', 'approved')->count();

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => trim($student->first_name.' '.$student->last_name),
                    'program' => $student->program?->code,
                    'hte' => $student->hte?->name ?? 'Not assigned',
                    'rendered_hours' => round($hours, 1),
                    'required_hours' => round($requiredHours, 1),
                    'progress_percent' => $requiredHours > 0 ? round(min(($hours / $requiredHours) * 100, 100), 1) : 0,
                    'last_attendance_date' => $lastAttendance?->date?->toDateString(),
                    'last_attendance_status' => $lastAttendance?->status ?? 'no_record',
                    'requirements_percent' => $requirementCount > 0 ? round(($approved / $requirementCount) * 100, 1) : 0,
                ];
            })->values();

        $pendingRegistrations = $students->where('registration_status', 'pending')->count();
        $missingJournals = max($expectedJournals - $submittedJournals, 0);
        $pendingRequirements = $requirements->where('status', 'pending')->count();
        $todayAbsent = Attendance::whereDate('date', now()->toDateString())
            ->where('status', 'absent')
            ->whereHas('student', fn ($query) => $query->where('program_id', $programId))
            ->count();

        $alerts = collect([
            $pendingRegistrations > 0 ? ['type' => 'warning', 'title' => 'Pending registrations', 'message' => $pendingRegistrations.' student registration(s) need review.', 'count' => $pendingRegistrations, 'link' => '/coordinator/students'] : null,
            $pendingRequirements > 0 ? ['type' => 'info', 'title' => 'Requirements awaiting review', 'message' => $pendingRequirements.' submitted requirement(s) are pending.', 'count' => $pendingRequirements, 'link' => '/coordinator/students'] : null,
            $missingJournals > 0 ? ['type' => 'danger', 'title' => 'Missing journal entries', 'message' => $missingJournals.' expected accomplishment report(s) are missing this month.', 'count' => $missingJournals, 'link' => '/coordinator/attendance'] : null,
            $expiringMoas->count() > 0 ? ['type' => 'warning', 'title' => 'MOAs expiring soon', 'message' => $expiringMoas->count().' approved MOA(s) expire within 30 days.', 'count' => $expiringMoas->count(), 'link' => '/coordinator/htes'] : null,
            $activeTravel > 0 ? ['type' => 'info', 'title' => 'Active travel sessions', 'message' => $activeTravel.' student travel session(s) are currently active.', 'count' => $activeTravel, 'link' => '/coordinator/travel'] : null,
            $todayAbsent > 0 ? ['type' => 'danger', 'title' => 'Absent today', 'message' => $todayAbsent.' intern(s) are marked absent today.', 'count' => $todayAbsent, 'link' => '/coordinator/attendance'] : null,
        ])->filter()->values();

        $announcements = Announcement::where('is_published', true)
            ->whereIn('target_audience', ['all', 'coordinators'])
            ->latest('published_at')
            ->limit(4)
            ->get(['id', 'title', 'content', 'published_at', 'created_at']);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'scope' => [
                'college' => $request->user()->college,
                'program' => $request->user()->program,
            ],
            'overview' => [
                'total_students' => $students->count(),
                'active' => $students->where('internship_status', 'active')->count(),
                'completed' => $students->where('internship_status', 'completed')->count(),
                'pending' => $students->where('internship_status', 'pending')->count(),
                'dropped' => $students->where('internship_status', 'dropped')->count(),
                'pending_registrations' => $pendingRegistrations,
            ],
            'active_interns' => $activeInterns,
            'attendance' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'holiday' => $holiday,
                'rate' => round($attendanceRate, 1),
                'total_hours' => round($monthAttendance->sum(fn (Attendance $record) => $this->renderedHours($record)), 1),
            ],
            'journals' => [
                'submitted' => $submittedJournals,
                'expected' => $expectedJournals,
                'missing' => $missingJournals,
                'students_with_submissions' => $monthAttendance->filter(fn (Attendance $record) => filled($record->am_activity) || filled($record->pm_activity))->pluck('student_id')->unique()->count(),
                'completion_rate' => $expectedJournals > 0 ? round(($submittedJournals / $expectedJournals) * 100, 1) : 0,
            ],
            'requirements' => [
                'total' => $requirementTotal,
                'approved' => $approvedRequirements,
                'pending' => $pendingRequirements,
                'rejected' => $requirements->where('status', 'rejected')->count(),
                'completion_rate' => $requirementTotal > 0 ? round(($approvedRequirements / $requirementTotal) * 100, 1) : 0,
            ],
            'hte_moa' => [
                'total_htes' => HTE::where('program_id', $programId)->count(),
                'active_htes' => HTE::where('program_id', $programId)->where('is_active', true)->count(),
                'assigned_htes' => $students->whereNotNull('hte_id')->pluck('hte_id')->unique()->count(),
                'moa_approved' => $approvedMoas->count(),
                'moa_pending' => $moas->where('status', 'pending')->count(),
                'moa_rejected' => $moas->where('status', 'rejected')->count(),
                'moa_expired' => $expiredMoas->count(),
                'expiring_soon' => $expiringMoas->map(fn (MOA $moa) => ['id' => $moa->id, 'hte' => $moa->hte?->name, 'expiration_date' => $moa->expiration_date->toDateString()])->values(),
            ],
            'travel' => [
                'active' => $activeTravel,
                'completed_this_month' => $monthTravel->where('status', 'completed')->count(),
                'cancelled_this_month' => $monthTravel->where('status', 'cancelled')->count(),
                'total_this_month' => $monthTravel->count(),
            ],
            'alerts' => $alerts,
            'announcements' => $announcements,
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
