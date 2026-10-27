<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\StudentInternship;
use App\Models\SystemNotification;
use App\Support\DeanAccess;
use App\Support\DeploymentActivation;
use App\Support\DeploymentWorkflow;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeploymentWorkflowController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, ['program_head', 'dean', 'vpaa'], true), 403);
        $query = ApprovalRecord::where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
            ->whereNotNull('deployment_stage')->whereNotNull('deployment_snapshot')
            ->with(['submitter', 'decisionMaker', 'deanForwarder', 'deploymentInternship.currentPlacement']);
        if ($role === 'program_head') {
            $programId = ProgramAccess::programId($request->user());
            $query->whereHas('deploymentInternship.academicSection', fn ($section) => $section->where('program_id', $programId));
        } elseif ($role === 'dean') {
            $collegeId = DeanAccess::collegeId($request->user());
            $query->whereHas('deploymentInternship.academicSection.program', fn ($program) => $program->where('college_id', $collegeId))
                ->whereIn('deployment_stage', [DeploymentWorkflow::DEAN, DeploymentWorkflow::VPAA]);
        } else {
            $query->where('deployment_stage', DeploymentWorkflow::VPAA);
        }

        $records = $query->latest('id')->get()->map(fn ($record) => DeploymentWorkflow::payload($record));

        return response()->json(['deployments' => $records, 'summary' => [
            'total' => $records->count(),
            'pending' => $records->where('is_current', true)->where('stage', $role === 'program_head' ? DeploymentWorkflow::PROGRAM_HEAD : DeploymentWorkflow::DEAN)->count(),
            'forwarded' => $records->where('stage', DeploymentWorkflow::VPAA)->count(),
        ]]);
    }

    public function approve(Request $request, ApprovalRecord $approval)
    {
        abort_unless($request->user()->role === 'program_head', 403);
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_DEPLOYMENT && $approval->deployment_internship_id, 404);
        abort_unless($approval->deploymentInternship?->academicSection?->program_id === ProgramAccess::programId($request->user()), 404);
        $data = $request->validate(['decision' => ['required', 'in:approved,forwarded'], 'remarks' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($approval, $request, $data) {
            $internship = StudentInternship::whereKey($approval->deployment_internship_id)->lockForUpdate()->firstOrFail();
            $locked = ApprovalRecord::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->deployment_stage === DeploymentWorkflow::PROGRAM_HEAD && $locked->status === 'pending'
                && DeploymentWorkflow::current($locked), 422, 'This deployment is no longer awaiting Program Head review.');
            $locked->update(['deployment_stage' => DeploymentWorkflow::DEAN, 'status' => 'approved',
                'decided_by' => $request->user()->id, 'decided_at' => now(), 'remarks' => $data['remarks'] ?? null]);
            $internship->update(['deployment_status' => 'approved']);
            DeploymentActivation::activateDue();
            DeploymentWorkflow::notifyDeans($internship);
            SystemNotification::sendToUser($locked->submitted_by, 'Deployment reviewed by Program Head',
                'The deployment was approved and sent to the Dean for forwarding.', 'approval', '/coordinator/htes?tab=deployments');
            SystemNotification::sendToUser($internship->student->user_id, 'Deployment approved',
                'Your HTE and department assignment was reviewed by the Program Head. OJT starts on the published start date.', 'approval', '/student/dashboard');
        });

        return response()->json(['message' => 'Deployment approved and forwarded to the Dean.', 'deployment' => DeploymentWorkflow::payload($approval->fresh())]);
    }

    public function forward(Request $request, ApprovalRecord $approval)
    {
        abort_unless($request->user()->role === 'dean', 403);
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_DEPLOYMENT && $approval->deployment_internship_id, 404);
        $collegeId = DeanAccess::collegeId($request->user());
        abort_unless($approval->deploymentInternship?->academicSection?->program?->college_id === $collegeId, 404);
        $request->validate(['decision' => ['prohibited']]);
        DB::transaction(function () use ($approval, $request) {
            StudentInternship::whereKey($approval->deployment_internship_id)->lockForUpdate()->firstOrFail();
            $locked = ApprovalRecord::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->deployment_stage === DeploymentWorkflow::DEAN && $locked->status === 'approved'
                && DeploymentWorkflow::current($locked), 422, 'Only current Program Head-approved deployments can be forwarded.');
            $locked->update(['deployment_stage' => DeploymentWorkflow::VPAA,
                'dean_forwarded_by' => $request->user()->id, 'dean_forwarded_at' => now()]);
            SystemNotification::sendToRole('vpaa', 'Student deployment forwarded',
                'The Dean forwarded a student HTE and department assignment for viewing.', 'approval', '/vpaa/deployments');
            SystemNotification::sendToUser($locked->decided_by, 'Deployment forwarded to VPAA',
                'The Dean forwarded the deployment to VPAA.', 'approval', '/program-head/documents?tab=deployments');
            SystemNotification::sendToUser($locked->deployment_snapshot['coordinator_id'] ?? $locked->submitted_by, 'Deployment forwarded to VPAA',
                'The Dean forwarded the deployment to VPAA.', 'approval', '/coordinator/htes?tab=deployments');
        });

        return response()->json(['message' => 'Deployment forwarded to VPAA.', 'deployment' => DeploymentWorkflow::payload($approval->fresh())]);
    }
}
