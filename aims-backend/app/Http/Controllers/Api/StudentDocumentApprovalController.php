<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentDocumentApproval;
use App\Models\StudentInternship;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\StudentDocumentFolderService;
use App\Support\DeanAccess;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentDocumentApprovalController extends Controller
{
    public function __construct(private readonly StudentDocumentFolderService $folders) {}

    public function endorse(Request $request, StudentInternship $internship)
    {
        return $this->saveEndorsement($request, $internship, false);
    }

    public function reEndorse(Request $request, StudentInternship $internship)
    {
        return $this->saveEndorsement($request, $internship, true);
    }

    private function saveEndorsement(Request $request, StudentInternship $internship, bool $reEndorse)
    {
        ProgramAccess::authorizeStudent($request->user(), $internship->student);

        $approval = DB::transaction(function () use ($request, $internship, $reEndorse) {
            StudentInternship::query()->whereKey($internship->id)->lockForUpdate()->firstOrFail();
            $existing = StudentDocumentApproval::query()
                ->where('student_internship_id', $internship->id)
                ->lockForUpdate()->first();

            if ($reEndorse ? $existing?->status !== StudentDocumentApproval::RETURNED : $existing !== null) {
                throw ValidationException::withMessages(['folder' => $reEndorse
                    ? 'Only a returned folder can be re-endorsed.'
                    : 'This internship folder has already been endorsed.']);
            }

            $summary = $this->folders->summary($internship);
            if (! $summary['complete']) {
                throw ValidationException::withMessages(['folder' => 'All active internship requirements must have uploads approved by the Program Head before endorsement.']);
            }

            $values = [
                'endorsed_by' => $request->user()->id,
                'endorsed_at' => now(),
                'status' => StudentDocumentApproval::FOR_DEAN_REVIEW,
                'dean_decided_by' => null,
                'dean_decided_at' => null,
                'dean_remarks' => null,
                'vpaa_reviewed_by' => null,
                'vpaa_reviewed_at' => null,
                'decided_by' => null,
                'decided_at' => null,
            ];

            if ($existing) {
                $existing->update($values);
                return $existing;
            }

            return StudentDocumentApproval::query()->create(['student_internship_id' => $internship->id] + $values);
        });

        $collegeId = $internship->academicSection?->program?->college_id;
        User::query()->forRole('dean')->where('is_active', true)
            ->whereHas('employeeProfile', fn ($profile) => $profile->where('college_id', $collegeId))
            ->pluck('id')->each(fn (int $userId) => SystemNotification::sendToUser(
                $userId, 'Student folder awaits Dean approval',
                'A Program Head endorsed a complete student document folder.', 'approval', '/dean/documents',
            ));

        return response()->json(['message' => 'Student folder endorsed to the Dean.', 'approval' => $this->payload($approval)]);
    }

    public function index(Request $request)
    {
        $role = $request->user()->role;
        $records = StudentDocumentApproval::query()
            ->with(['internship.student.program', 'endorser', 'decisionMaker', 'deanDecisionMaker', 'vpaaReviewer'])
            ->when($role === 'dean', fn ($query) => $query->whereHas('internship.academicSection.program',
                fn ($program) => $program->where('college_id', DeanAccess::collegeId($request->user()))))
            ->when($role === 'vpaa', fn ($query) => $query->whereIn('status', [
                StudentDocumentApproval::FOR_REVIEW,
                StudentDocumentApproval::REVIEWED,
                StudentDocumentApproval::FINAL_APPROVED,
            ]))
            ->latest('endorsed_at')->get();

        if ($role === 'dean') {
            return response()->json([
                'summary' => [
                    'for_dean_review' => $records->where('status', StudentDocumentApproval::FOR_DEAN_REVIEW)->count(),
                    'approved' => $records->whereIn('status', [StudentDocumentApproval::FOR_REVIEW, StudentDocumentApproval::REVIEWED])->count(),
                    'returned' => $records->where('status', StudentDocumentApproval::RETURNED)->count(),
                ],
                'folders' => $records->where('status', StudentDocumentApproval::FOR_DEAN_REVIEW)->map(fn ($record) => $this->payload($record))->values(),
                'history' => $records->where('status', '!=', StudentDocumentApproval::FOR_DEAN_REVIEW)->map(fn ($record) => $this->payload($record))->values(),
            ]);
        }

        return response()->json([
            'summary' => [
                'for_review' => $records->where('status', StudentDocumentApproval::FOR_REVIEW)->count(),
                'reviewed' => $records->where('status', StudentDocumentApproval::REVIEWED)->count(),
                'final_approved' => $records->where('status', StudentDocumentApproval::FINAL_APPROVED)->count(),
            ],
            'folders' => $records->where('status', StudentDocumentApproval::FOR_REVIEW)->map(fn ($record) => $this->payload($record))->values(),
            'history' => $records->whereIn('status', [StudentDocumentApproval::REVIEWED, StudentDocumentApproval::FINAL_APPROVED])
                ->sortByDesc(fn ($record) => $record->vpaa_reviewed_at ?? $record->decided_at)
                ->map(fn ($record) => $this->payload($record))->values(),
        ]);
    }

    public function history()
    {
        return response()->json(StudentDocumentApproval::query()
            ->with(['internship.student.program', 'endorser', 'decisionMaker', 'deanDecisionMaker', 'vpaaReviewer'])
            ->whereIn('status', [StudentDocumentApproval::REVIEWED, StudentDocumentApproval::FINAL_APPROVED])
            ->latest('updated_at')->get()->map(fn ($record) => $this->payload($record))->values());
    }

    public function show(Request $request, StudentDocumentApproval $approval)
    {
        $this->authorizeVisible($request, $approval);
        return response()->json($this->payload($approval, true));
    }

    public function deanApprove(Request $request, StudentDocumentApproval $approval)
    {
        return $this->decideAsDean($request, $approval, StudentDocumentApproval::FOR_REVIEW);
    }

    public function deanReturn(Request $request, StudentDocumentApproval $approval)
    {
        return $this->decideAsDean($request, $approval, StudentDocumentApproval::RETURNED);
    }

    private function decideAsDean(Request $request, StudentDocumentApproval $approval, string $status)
    {
        $this->authorizeVisible($request, $approval);
        $data = $request->validate([
            'remarks' => [$status === StudentDocumentApproval::RETURNED ? 'required' : 'nullable', 'string', 'max:2000'],
        ]);
        $approval = DB::transaction(function () use ($request, $approval, $status, $data) {
            $locked = StudentDocumentApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== StudentDocumentApproval::FOR_DEAN_REVIEW) {
                throw ValidationException::withMessages(['folder' => 'This folder is no longer awaiting Dean review.']);
            }
            if ($status === StudentDocumentApproval::FOR_REVIEW
                && ! $this->folders->summary($locked->internship)['complete']) {
                throw ValidationException::withMessages(['folder' => 'The folder no longer has all required documents approved.']);
            }
            if ($status === StudentDocumentApproval::FOR_REVIEW
                && $this->folders->checklist($locked->internship)->contains(function (array $entry) {
                    $path = $this->privateStoragePath($entry['requirement']?->file_path);
                    return ! $path || ! $this->privateDisk()->exists($path);
                })) {
                throw ValidationException::withMessages(['folder' => 'A required file is unavailable. Restore it before final approval.']);
            }
            $locked->update([
                'status' => $status,
                'dean_decided_by' => $request->user()->id,
                'dean_decided_at' => now(),
                'dean_remarks' => $data['remarks'] ?? null,
            ]);
            return $locked;
        });

        SystemNotification::sendToUser($approval->endorsed_by, 'Dean folder decision recorded',
            $status === StudentDocumentApproval::FOR_REVIEW
                ? 'The student document folder received final Dean approval.'
                : 'The Dean returned the student document folder: '.$data['remarks'],
            'approval', '/program-head/documents');
        if ($status === StudentDocumentApproval::FOR_REVIEW) {
            SystemNotification::sendToRole('vpaa', 'Student folder ready for review',
                'A Dean finally approved a student document folder.', 'approval', '/vpaa/approvals');
        }

        return response()->json([
            'message' => $status === StudentDocumentApproval::FOR_REVIEW
                ? 'Folder finally approved and sent to VPAA for review.' : 'Folder returned to the Program Head.',
            'approval' => $this->payload($approval),
        ]);
    }

    public function markReviewed(Request $request, StudentDocumentApproval $approval)
    {
        $this->authorizeVisible($request, $approval);
        $approval = DB::transaction(function () use ($request, $approval) {
            $locked = StudentDocumentApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== StudentDocumentApproval::FOR_REVIEW || ! $locked->dean_decided_by) {
                throw ValidationException::withMessages(['folder' => 'Dean final approval is required before VPAA review.']);
            }
            $locked->update([
                'status' => StudentDocumentApproval::REVIEWED,
                'vpaa_reviewed_by' => $request->user()->id,
                'vpaa_reviewed_at' => now(),
            ]);
            return $locked;
        });

        SystemNotification::sendToUser($approval->endorsed_by, 'VPAA folder review recorded',
            'The Dean-approved student document folder was marked reviewed by VPAA.',
            'approval', '/program-head/documents');

        return response()->json(['message' => 'Folder marked as reviewed.', 'approval' => $this->payload($approval)]);
    }

    public function viewFile(Request $request, StudentDocumentApproval $approval, int $requirement)
    {
        $this->authorizeVisible($request, $approval);
        $item = $this->folders->checklist($approval->internship)
            ->first(fn (array $entry) => $entry['requirement']?->id === $requirement);
        abort_unless($item && $item['requirement']->file_path, 404, 'File unavailable.');
        $path = $this->privateStoragePath($item['requirement']->file_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'File unavailable.');

        return $this->privateDisk()->response($path);
    }

    private function authorizeVisible(Request $request, StudentDocumentApproval $approval): void
    {
        if ($request->user()->role === 'dean') {
            $collegeId = DeanAccess::collegeId($request->user());
            abort_unless($approval->internship?->academicSection?->program?->college_id === $collegeId, 404);
            return;
        }
        abort_unless($request->user()->role === 'vpaa'
            && in_array($approval->status, [
                StudentDocumentApproval::FOR_REVIEW,
                StudentDocumentApproval::REVIEWED,
                StudentDocumentApproval::FINAL_APPROVED,
            ], true), 404);
    }

    private function payload(StudentDocumentApproval $approval, bool $includeRequirements = false): array
    {
        $approval->loadMissing(['internship.student.program', 'endorser', 'decisionMaker', 'deanDecisionMaker', 'vpaaReviewer']);
        $student = $approval->internship?->student;
        $summary = $this->folders->summary($approval->internship);
        $result = [
            'id' => $approval->id,
            'student_internship_id' => $approval->student_internship_id,
            'student' => [
                'id' => $student?->id,
                'name' => trim(($student?->first_name ?? '').' '.($student?->middle_name ?? '').' '.($student?->last_name ?? '')),
                'student_id' => $student?->student_id,
                'program' => $student?->program?->name,
                'program_code' => $student?->program?->code,
            ],
            'approved_count' => $summary['approved'],
            'required_count' => $summary['total'],
            'status' => $approval->status,
            'endorsed_by' => $approval->endorser?->name,
            'endorsed_at' => $approval->endorsed_at?->toIso8601String(),
            'dean_decided_by' => $approval->deanDecisionMaker?->name,
            'dean_decided_at' => $approval->dean_decided_at?->toIso8601String(),
            'dean_remarks' => $approval->dean_remarks,
            'vpaa_reviewed_by' => $approval->vpaaReviewer?->name,
            'vpaa_reviewed_at' => $approval->vpaa_reviewed_at?->toIso8601String(),
            'decided_by' => $approval->decisionMaker?->name,
            'decided_at' => $approval->decided_at?->toIso8601String(),
        ];

        if ($includeRequirements) {
            $result['requirements'] = $summary['items']->map(function (array $entry) {
                $requirement = $entry['requirement'];
                $path = $this->privateStoragePath($requirement?->file_path);
                return [
                    'id' => $requirement?->id,
                    'name' => $entry['definition']->name,
                    'status' => $requirement?->status ?? 'pending',
                    'file_available' => $path && $this->privateDisk()->exists($path),
                ];
            })->values();
        }

        return $result;
    }
}
