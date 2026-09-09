<?php

namespace App\Http\Controllers\Api;

use App\Models\ApprovalRecord;
use App\Models\College;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\TravelReport;
use Illuminate\Http\Request;

class VPAADashboardController extends UniversityDashboardController
{
    public function index(Request $request)
    {
        $response = parent::index($request);
        $dashboard = $response->getData(true);

        $studentIds = Student::query()
            ->when($request->integer('college_id'), fn ($query, $value) => $query->where('college_id', $value))
            ->when($request->integer('program_id'), fn ($query, $value) => $query->where('program_id', $value))
            ->when($request->integer('hte_id'), fn ($query, $value) => $query->where('hte_id', $value))
            ->when($request->string('academic_year')->toString(), fn ($query, $value) => $query->where('internship_year', $value))
            ->when($request->string('semester')->toString(), fn ($query, $value) => $query->where('internship_semester', $value))
            ->when($request->string('internship_status')->toString(), fn ($query, $value) => $query->where('internship_status', $value))
            ->pluck('id');
        $moaQuery = MOA::query()
            ->when($request->integer('college_id'), fn ($query, $value) => $query->where('college_id', $value))
            ->when($request->integer('program_id'), fn ($query, $value) => $query->where('program_id', $value))
            ->when($request->integer('hte_id'), fn ($query, $value) => $query->where('hte_id', $value));
        $pendingMoas = (clone $moaQuery)->where('status', 'pending')->count();
        $approvedMoas = (clone $moaQuery)->where('status', 'approved')->count();
        $deploymentApprovals = ApprovalRecord::query()
            ->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
            ->whereIn('subject_id', $studentIds)
            ->whereHas('submitter', fn ($query) => $query->where('role', 'program_head'))
            ->with(['submitter:id,name,role', 'decisionMaker:id,name,role'])
            ->latest('submitted_at')
            ->get();
        $travelReports = TravelReport::query()
            ->whereHas('travelLog.participants', fn ($query) => $query->whereIn('students.id', $studentIds))
            ->with(['travelLog.hte:id,name', 'travelLog.participants.program:id,code', 'travelLog.participants.academicSection:id,name', 'travelLog.checkpoints', 'validations'])
            ->get();
        $deploymentStudents = Student::query()
            ->whereIn('id', $studentIds)
            ->with(['program:id,code', 'academicSection:id,name', 'hte:id,name'])
            ->get()
            ->keyBy('id');

        $dashboard['overview'] = array_merge($dashboard['overview'], [
            'colleges' => College::count(),
            'active_programs' => Program::where('is_active', true)->count(),
            'approved_moas' => $approvedMoas,
        ]);

        $dashboard['pending_approvals']['moas'] = $pendingMoas;
        $dashboard['pending_approvals']['deployments'] = $deploymentApprovals->where('status', 'pending')->count();
        $dashboard['pending_approvals']['travel_reports'] = $travelReports->where('status', 'pending_vpaa')->count();
        $dashboard['pending_approvals']['total'] += $pendingMoas
            + $dashboard['pending_approvals']['deployments']
            + $dashboard['pending_approvals']['travel_reports'];
        $dashboard['deployment_approvals'] = [
            'pending' => $deploymentApprovals->where('status', 'pending')->count(),
            'approved' => $deploymentApprovals->where('status', 'approved')->count(),
            'rejected' => $deploymentApprovals->where('status', 'rejected')->count(),
            'history' => $deploymentApprovals->take(50)->map(fn (ApprovalRecord $approval) => [
                'id' => $approval->id,
                'status' => $approval->status,
                'submitted_by' => $approval->submitter?->name,
                'submitted_at' => $approval->submitted_at?->toIso8601String(),
                'decided_by' => $approval->decisionMaker?->name,
                'decided_at' => $approval->decided_at?->toIso8601String(),
                'remarks' => $approval->remarks,
            ])->values(),
        ];
        $dashboard['deployment_validations'] = $deploymentApprovals->map(function (ApprovalRecord $approval) use ($deploymentStudents) {
            $student = $deploymentStudents->get($approval->subject_id);

            return [
                'approval_id' => $approval->id,
                'batch' => $student?->academicSection?->name ?? $student?->section,
                'program' => $student?->program?->code,
                'hte' => $student?->hte?->name,
                'student_id' => $student?->student_id,
                'student' => $student ? trim($student->first_name.' '.$student->last_name) : null,
                'proposed_date' => $student?->ojt_start_date?->toDateString(),
                'working_schedule' => $student ? $this->studentSchedule($student) : null,
                'coordinator_submission' => $approval->submitted_at?->toIso8601String() ?? $approval->created_at?->toIso8601String(),
                'program_head_review' => $approval->submitter?->role === 'program_head' ? 'forwarded' : 'not_forwarded',
                'program_head_remarks' => $approval->submitter?->role === 'program_head' ? $approval->remarks : null,
                'vpaa_validation' => $approval->status,
                'deployment_status' => $student?->schedule_status,
            ];
        })->values();
        $dashboard['travel_validations'] = $travelReports->map(function (TravelReport $report) {
            $travel = $report->travelLog;
            $student = $travel?->participants->first();
            $programHead = $report->validations->where('reviewer_role', 'program_head')->sortByDesc('reviewed_at')->first();
            $vpaa = $report->validations->where('reviewer_role', 'vpaa')->sortByDesc('reviewed_at')->first();

            return [
                'report_id' => $report->id,
                'reference' => $report->report_number,
                'program' => $student?->program?->code,
                'destination' => $travel?->hte?->name ?: $travel?->destination,
                'students' => $travel?->participants->map(fn (Student $participant) => trim($participant->first_name.' '.$participant->last_name))->implode(', '),
                'travel_date' => $travel?->start_time?->toDateString(),
                'departure_arrival' => collect([$travel?->departed_at?->format('g:i A') ?? $travel?->start_time?->format('g:i A'), $travel?->arrived_at?->format('g:i A') ?? $travel?->end_time?->format('g:i A')])->filter()->implode(' - '),
                'route_checkpoints' => $travel?->checkpoints->sortBy('sequence')->pluck('checkpoint_name')->filter()->implode(', ') ?: $travel?->route_notes,
                'photo_evidence' => filled($travel?->arrival_photo_path) || $travel?->checkpoints->contains(fn ($checkpoint) => filled($checkpoint->photo_path)),
                'coordinator_submission' => $report->submitted_at?->toIso8601String(),
                'program_head_review' => $programHead?->action ?? 'not_reviewed',
                'program_head_remarks' => $programHead?->comments ?: $programHead?->reason,
                'vpaa_validation' => $vpaa?->action ?? 'not_reviewed',
                'approval_status' => $report->status,
            ];
        })->values();
        $dashboard['travel']['reports_awaiting_validation'] = $travelReports->where('status', 'pending_vpaa')->count();
        $dashboard['travel']['reports_approved'] = $travelReports->where('status', 'final_validated')->count();
        $dashboard['travel']['reports_returned'] = $travelReports->whereIn('status', ['for_revision', 'returned_by_vpaa'])->count();

        return response()->json($dashboard);
    }

    private function studentSchedule(Student $student): string
    {
        $days = collect($student->work_days ?? [])->map(fn (string $day) => ucfirst(substr($day, 0, 3)))->implode(', ');

        return collect([
            $days ?: null,
            $student->official_am_start && $student->official_am_end ? 'AM '.$student->official_am_start.'-'.$student->official_am_end : null,
            $student->official_pm_start && $student->official_pm_end ? 'PM '.$student->official_pm_start.'-'.$student->official_pm_end : null,
        ])->filter()->implode(' | ') ?: 'Not configured';
    }
}
