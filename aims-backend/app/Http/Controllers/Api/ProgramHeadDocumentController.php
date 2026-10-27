<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\StudentDocumentApproval;
use App\Models\StudentInternship;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\StudentDocumentFolderService;
use App\Support\ProgramAccess;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramHeadDocumentController extends Controller
{
    public function __construct(private readonly StudentDocumentFolderService $folders) {}

    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $requirements = InternshipRequirement::with([
            'student.user',
            'student.program',
            'student.college',
            'student.hte',
            'reviewer',
        ])
            ->whereHas('student', fn ($query) => $query->forProgram($programId))
            ->whereNotNull('file_path')
            ->latest('updated_at')
            ->get();

        $moas = MOA::with(['hte', 'program.college', 'approver', 'vpaaReviewer', 'programReviewer', 'approvalRecord'])
            ->where('program_id', $programId)
            ->latest('updated_at')
            ->get();

        $today = now()->startOfDay();
        $internshipIds = $requirements->pluck('student_internship_id')->filter()->unique();
        $internships = StudentInternship::with('student')->whereIn('id', $internshipIds)->get();
        $folderApprovals = StudentDocumentApproval::whereIn('student_internship_id', $internshipIds)
            ->get()->keyBy('student_internship_id');

        return response()->json([
            'summary' => [
                'submitted_requirements' => $requirements->count(),
                'pending_requirements' => $requirements->where('status', 'pending')->count(),
                'approved_requirements' => $requirements->where('status', 'approved')->count(),
                'rejected_requirements' => $requirements->where('status', 'rejected')->count(),
                'total_moas' => $moas->count(),
                'pending_moas' => $moas->where('program_status', 'pending')->count(),
                'approved_moas' => $moas->where('status', 'approved')->count(),
                'expiring_moas' => $moas->filter(fn (MOA $moa) => $moa->status === 'approved'
                    && $moa->expiration_date->gte($today)
                    && $moa->expiration_date->lte($today->copy()->addDays(60)))->count(),
            ],
            'requirements' => $requirements->map(fn (InternshipRequirement $requirement) => $this->requirementPayload($requirement))->values(),
            'folder_readiness' => $internships->mapWithKeys(function (StudentInternship $internship) use ($folderApprovals) {
                $summary = $this->folders->summary($internship);
                $approval = $folderApprovals->get($internship->id);
                return [$internship->id => [
                    'total' => $summary['total'],
                    'approved' => $summary['approved'],
                    'complete' => $summary['complete'],
                    'approval_status' => $approval?->status,
                ]];
            }),
            'moas' => $moas->map(fn (MOA $moa) => $this->moaPayload($moa, $today))->values(),
        ]);
    }

    public function reviewRequirement(Request $request, InternshipRequirement $requirement)
    {
        ProgramAccess::authorizeRequirement($request->user(), $requirement);
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'feedback' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $requirement, $validated): void {
            StudentInternship::query()->whereKey($requirement->student_internship_id)->lockForUpdate()->firstOrFail();
            $locked = InternshipRequirement::query()->whereKey($requirement->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->file_path, 422, 'The student has not uploaded this requirement yet.');
            abort_if($locked->reviewer?->role !== 'program_head'
                && ($locked->reviewer?->role !== 'coordinator' || $locked->status !== 'approved'),
                422, 'The coordinator must approve this requirement before Program Head review.');
            abort_if($locked->isConsent() && ($locked->student?->consent_status !== 'done' || $locked->status !== 'approved'),
                422, 'The coordinator must accept this consent before Program Head review.');

            $folderStatus = StudentDocumentApproval::where('student_internship_id', $locked->student_internship_id)->value('status');
            abort_if(in_array($folderStatus, [StudentDocumentApproval::FOR_DEAN_REVIEW, StudentDocumentApproval::FOR_REVIEW,
                StudentDocumentApproval::REVIEWED, StudentDocumentApproval::FINAL_APPROVED], true),
                422, 'This student folder is locked during final approval and VPAA review.');

            $locked->update([
                'status' => $validated['decision'],
                'feedback' => $validated['feedback'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Requirement '.$validated['decision'].'.',
            'requirement' => $this->requirementPayload($requirement->fresh([
                'internship.academicSection',
                'student.user',
                'student.program',
                'student.college',
                'student.hte',
                'reviewer',
            ])),
        ]);
    }

    public function downloadRequirement(Request $request, InternshipRequirement $requirement)
    {
        ProgramAccess::authorizeRequirement($request->user(), $requirement);
        $path = $this->privateStoragePath($requirement->file_path);
        if (! $path || ! $this->privateDisk()->exists($path)) {
            return response()->json(['message' => 'Requirement file not found.'], 404);
        }

        return $this->privateDisk()->download($path);
    }

    public function reviewMoa(Request $request, MOA $moa)
    {
        ProgramAccess::authorizeMoa($request->user(), $moa);
        $pending = $moa->program_status === 'pending' && $moa->status === 'pending';
        $completed = $moa->program_status === 'endorsed' && $moa->status === 'approved'
            && $moa->vpaa_reviewed_at !== null;
        if (! $pending && ! $completed) {
            return response()->json(['message' => 'This MOA has already been reviewed.'], 422);
        }
        $validated = $request->validate([
            'decision' => ['required', 'in:endorsed,returned'],
            'feedback' => ['nullable', 'required_if:decision,returned', 'string', 'max:2000'],
        ]);

        $endorsed = $validated['decision'] === 'endorsed';
        if ($completed && $endorsed) {
            return response()->json(['message' => 'A completed MOA can only be returned for revision.'], 422);
        }
        if ($endorsed && $moa->expiration_date->lt(today())) {
            return response()->json(['message' => 'This MOA has expired. Submit a renewed MOA before endorsement.'], 422);
        }

        $moa->update([
            'program_status' => $endorsed ? 'endorsed' : 'returned',
            'program_feedback' => $validated['feedback'] ?? null,
            'program_reviewed_by' => $request->user()->id,
            'program_reviewed_at' => now(),
            'status' => 'pending',
            ...($completed ? [
                'dean_endorsed_by' => null,
                'dean_endorsed_at' => null,
                'dean_remarks' => null,
                'approved_by' => null,
                'approved_at' => null,
                'vpaa_reviewed_by' => null,
                'vpaa_reviewed_at' => null,
            ] : []),
        ]);

        if ($endorsed) {
            ApprovalRecord::submit(ApprovalRecord::TYPE_MOA, $moa->id, $request->user()->id);
            User::query()->forRole('dean')->where('is_active', true)
                ->whereHas('employeeProfile', fn ($profile) => $profile->where('college_id', $moa->program?->college_id))
                ->pluck('id')->each(fn (int $userId) => SystemNotification::sendToUser($userId, 'MOA ready for Dean final approval', ($moa->hte?->name ?? 'An HTE').' MOA is ready for review.', 'approval', '/dean/moas'));
        }

        return response()->json([
            'message' => $endorsed ? 'MOA sent to the Dean for final approval.' : 'MOA returned to the coordinator.',
            'moa' => $this->moaPayload($moa->fresh(['hte', 'program.college', 'approver', 'programReviewer']), now()->startOfDay()),
        ]);
    }

    public function downloadMoa(Request $request, MOA $moa)
    {
        ProgramAccess::authorizeMoa($request->user(), $moa);
        $path = $this->privateStoragePath($moa->file_path);
        if (! $path || ! $this->privateDisk()->exists($path)) {
            return response()->json(['message' => 'MOA file not found.'], 404);
        }

        return $this->privateDisk()->download($path);
    }

    private function requirementPayload(InternshipRequirement $requirement): array
    {
        return [
            'id' => $requirement->id,
            'student_internship_id' => $requirement->student_internship_id,
            'requirement_name' => $requirement->requirement_name,
            'file_path' => $requirement->file_path,
            'file_type' => $requirement->file_type,
            'status' => $requirement->status,
            'program_head_approved' => $requirement->status === 'approved' && $requirement->reviewer?->role === 'program_head',
            'can_program_head_review' => ($requirement->reviewer?->role === 'program_head'
                || ($requirement->reviewer?->role === 'coordinator' && $requirement->status === 'approved'))
                && (! $requirement->isConsent()
                    || ($requirement->student?->consent_status === 'done' && $requirement->status === 'approved')),
            'program_head_endorsed' => StudentDocumentApproval::query()
                ->where('student_internship_id', $requirement->student_internship_id)
                ->whereIn('status', [StudentDocumentApproval::FOR_DEAN_REVIEW, StudentDocumentApproval::FOR_REVIEW,
                    StudentDocumentApproval::REVIEWED, StudentDocumentApproval::FINAL_APPROVED])
                ->exists(),
            'feedback' => $requirement->feedback,
            'submitted_at' => $this->requirementSubmittedAt($requirement),
            'reviewed_at' => $requirement->reviewed_at?->toIso8601String(),
            'reviewer' => $requirement->reviewer?->name,
            'student' => [
                'id' => $requirement->student?->id,
                'student_id' => $requirement->student?->student_id,
                'name' => trim(($requirement->student?->first_name ?? '').' '.($requirement->student?->last_name ?? '')),
                'program' => $requirement->student?->program?->code,
                'college' => $requirement->student?->college?->code,
                'hte' => $requirement->student?->hte?->name ?? 'Not deployed',
            ],
        ];
    }

    private function requirementSubmittedAt(InternshipRequirement $requirement): ?string
    {
        $path = $this->privateStoragePath($requirement->file_path);
        $disk = $this->privateDisk();

        return $path && $disk->exists($path)
            ? \Carbon\Carbon::createFromTimestamp($disk->lastModified($path))->toIso8601String()
            : $requirement->updated_at?->toIso8601String();
    }

    private function moaPayload(MOA $moa, CarbonInterface $today): array
    {
        $expired = $moa->expiration_date->lt($today);

        return [
            'id' => $moa->id,
            'hte' => $moa->hte?->name,
            'program' => $moa->program?->code,
            'program_id' => $moa->program_id,
            'college' => $moa->college?->code,
            'file_path' => $moa->file_path,
            'effective_date' => $moa->effective_date->toDateString(),
            'expiration_date' => $moa->expiration_date->toDateString(),
            'status' => $moa->status,
            'program_status' => $moa->program_status,
            'program_feedback' => $moa->program_feedback,
            'dean_endorsed_at' => $moa->dean_endorsed_at?->toIso8601String(),
            'dean_approved_by' => $moa->approver?->role === 'dean' ? $moa->approver->name : null,
            'dean_approved_at' => $moa->approver?->role === 'dean' ? $moa->approved_at?->toIso8601String() : null,
            'vpaa_reviewed_by' => $moa->vpaaReviewer?->name,
            'vpaa_reviewed_at' => $moa->vpaa_reviewed_at?->toIso8601String(),
            'dean_remarks' => $moa->dean_remarks,
            'remarks' => $moa->program_status === 'pending' ? null : $moa->approvalRecord?->remarks,
            'computed_status' => $expired ? 'expired' : $moa->status,
            'validity' => $expired ? 'expired' : ($moa->effective_date->gt($today) ? 'upcoming' : ($moa->expiration_date->lte($today->copy()->addDays(60)) ? 'expiring soon' : 'valid')),
            'days_remaining' => (int) $today->diffInDays($moa->expiration_date, false),
            'reviewed_at' => $moa->program_reviewed_at?->toIso8601String(),
            'reviewer' => $moa->programReviewer?->name,
            'approved_at' => $moa->approved_at?->toIso8601String(),
            'approver' => $moa->approver?->name,
            'updated_at' => $moa->updated_at?->toIso8601String(),
        ];
    }
}
