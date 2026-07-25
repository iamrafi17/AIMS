<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\InternshipRequirement;
use App\Models\Student;
use App\Models\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VPAAApprovalController extends Controller
{
    public function index()
    {
        $pending = ApprovalRecord::with(['submitter'])
            ->where('status', 'pending')
            ->latest('updated_at')
            ->get();

        $requirements = InternshipRequirement::with([
            'student.user',
            'student.program',
            'student.college',
            'student.hte',
            'reviewer',
        ])->whereIn('id', $pending->where('subject_type', ApprovalRecord::TYPE_DOCUMENT)->pluck('subject_id'))->get()->keyBy('id');

        $students = Student::with(['user', 'program', 'college', 'hte'])
            ->whereIn('id', $pending->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)->pluck('subject_id'))
            ->get()
            ->keyBy('id');

        $documents = $pending
            ->where('subject_type', ApprovalRecord::TYPE_DOCUMENT)
            ->map(fn (ApprovalRecord $approval) => $requirements->has($approval->subject_id)
                ? $this->documentPayload($approval, $requirements[$approval->subject_id])
                : null)
            ->filter()
            ->values();

        $deployments = $pending
            ->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
            ->map(fn (ApprovalRecord $approval) => $students->has($approval->subject_id) && $students[$approval->subject_id]->hte
                ? $this->deploymentPayload($approval, $students[$approval->subject_id])
                : null)
            ->filter()
            ->values();

        $history = ApprovalRecord::with(['submitter', 'decisionMaker'])
            ->whereIn('status', ['approved', 'rejected'])
            ->latest('decided_at')
            ->limit(200)
            ->get();

        $historyRequirements = InternshipRequirement::with(['student.program', 'student.college'])
            ->whereIn('id', $history->where('subject_type', ApprovalRecord::TYPE_DOCUMENT)->pluck('subject_id'))
            ->get()
            ->keyBy('id');
        $historyStudents = Student::with(['program', 'college', 'hte'])
            ->whereIn('id', $history->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)->pluck('subject_id'))
            ->get()
            ->keyBy('id');

        $historyPayload = $history->map(function (ApprovalRecord $approval) use ($historyRequirements, $historyStudents) {
            $subject = $approval->subject_type === ApprovalRecord::TYPE_DOCUMENT
                ? $historyRequirements->get($approval->subject_id)
                : $historyStudents->get($approval->subject_id);

            return $subject ? $this->historyPayload($approval, $subject) : null;
        })->filter()->values();

        return response()->json([
            'summary' => [
                'pending_documents' => $documents->count(),
                'pending_deployments' => $deployments->count(),
                'approved' => $history->where('status', 'approved')->count(),
                'rejected' => $history->where('status', 'rejected')->count(),
                'decided_today' => $history->filter(fn (ApprovalRecord $record) => $record->decided_at?->isToday())->count(),
            ],
            'documents' => $documents,
            'deployments' => $deployments,
            'history' => $historyPayload,
        ]);
    }

    public function review(Request $request, ApprovalRecord $approval)
    {
        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages([
                'approval' => 'This request has already been decided.',
            ]);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'remarks' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($approval, $request, $validated) {
            if ($approval->subject_type === ApprovalRecord::TYPE_DOCUMENT) {
                $requirement = InternshipRequirement::findOrFail($approval->subject_id);
                if (! $requirement->file_path) {
                    throw ValidationException::withMessages(['document' => 'The endorsed document file is no longer available.']);
                }

                if ($validated['decision'] === 'rejected') {
                    $requirement->update([
                        'status' => 'rejected',
                        'feedback' => $validated['remarks'],
                        'reviewed_by' => $request->user()->id,
                        'reviewed_at' => now(),
                    ]);
                }
            } elseif ($approval->subject_type === ApprovalRecord::TYPE_DEPLOYMENT) {
                Student::findOrFail($approval->subject_id)->update([
                    'schedule_status' => $validated['decision'],
                ]);
            } else {
                throw ValidationException::withMessages(['approval' => 'Unsupported approval request type.']);
            }

            $approval->update([
                'status' => $validated['decision'],
                'remarks' => $validated['remarks'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
        });
        SystemNotification::sendToUser(
            $approval->submitted_by,
            'VPAA decision recorded',
            ucfirst($approval->subject_type).' request was '.$validated['decision'].'.',
            'approval',
            $approval->subject_type === ApprovalRecord::TYPE_DEPLOYMENT ? '/coordinator/htes' : '/program-head/documents',
        );

        return response()->json([
            'message' => ucfirst($approval->subject_type).' request '.$validated['decision'].'.',
            'approval' => $approval->fresh(['submitter', 'decisionMaker']),
        ]);
    }

    public function download(ApprovalRecord $approval)
    {
        if ($approval->subject_type !== ApprovalRecord::TYPE_DOCUMENT) {
            return response()->json(['message' => 'This approval does not contain a document.'], 422);
        }

        $requirement = InternshipRequirement::findOrFail($approval->subject_id);
        if (! $requirement->file_path || ! Storage::disk('public')->exists($requirement->file_path)) {
            return response()->json(['message' => 'Endorsed document file not found.'], 404);
        }

        return Storage::disk('public')->download($requirement->file_path);
    }

    private function documentPayload(ApprovalRecord $approval, InternshipRequirement $requirement): array
    {
        return [
            'approval_id' => $approval->id,
            'document_id' => $requirement->id,
            'document_name' => $requirement->requirement_name,
            'file_type' => $requirement->file_type,
            'endorsed_at' => $approval->created_at?->toIso8601String(),
            'endorsed_by' => $approval->submitter?->name ?? $requirement->reviewer?->name,
            'student' => $this->studentSummary($requirement->student),
        ];
    }

    private function deploymentPayload(ApprovalRecord $approval, Student $student): array
    {
        return [
            'approval_id' => $approval->id,
            'requested_at' => $approval->created_at?->toIso8601String(),
            'requested_by' => $approval->submitter?->name,
            'student' => $this->studentSummary($student),
            'hte' => [
                'id' => $student->hte?->id,
                'name' => $student->hte?->name,
                'address' => $student->hte?->address,
                'contact_person' => $student->hte?->contact_person,
            ],
            'schedule' => [
                'start_date' => $student->ojt_start_date?->toDateString(),
                'end_date' => $student->ojt_end_date?->toDateString(),
                'required_hours' => (float) $student->required_ojt_hours,
                'am_start' => $student->official_am_start,
                'am_end' => $student->official_am_end,
                'pm_start' => $student->official_pm_start,
                'pm_end' => $student->official_pm_end,
                'work_days' => $student->work_days ?? [],
            ],
        ];
    }

    private function historyPayload(ApprovalRecord $approval, InternshipRequirement|Student $subject): array
    {
        $isDocument = $approval->subject_type === ApprovalRecord::TYPE_DOCUMENT;

        return [
            'id' => $approval->id,
            'type' => $approval->subject_type,
            'subject' => $isDocument ? $subject->requirement_name : ($subject->hte?->name ?? 'Student Deployment'),
            'student' => $this->studentSummary($isDocument ? $subject->student : $subject),
            'status' => $approval->status,
            'remarks' => $approval->remarks,
            'submitted_by' => $approval->submitter?->name,
            'decided_by' => $approval->decisionMaker?->name,
            'decided_at' => $approval->decided_at?->toIso8601String(),
        ];
    }

    private function studentSummary(?Student $student): array
    {
        return [
            'id' => $student?->id,
            'student_id' => $student?->student_id,
            'name' => trim(($student?->first_name ?? '').' '.($student?->middle_name ?? '').' '.($student?->last_name ?? '')),
            'program' => $student?->program?->code,
            'college' => $student?->college?->code,
            'section' => $student?->section,
        ];
    }
}
