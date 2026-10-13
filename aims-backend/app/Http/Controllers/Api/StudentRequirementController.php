<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\InternshipRequirement;
use App\Models\StudentDocumentApproval;
use App\Models\StudentInternship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentRequirementController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        InternshipRequirement::ensureForStudent($student->id);

        $requirements = InternshipRequirement::where('student_id', $student->id)
            ->forCurrentInternship()
            ->whereHas('definition', fn ($query) => $query->where('is_active', true))
            ->with('definition')
            ->join('program_requirements', 'program_requirements.id', '=', 'internship_requirements.program_requirement_id')
            ->orderBy('program_requirements.sort_order')
            ->select('internship_requirements.*')
            ->get();

        return response()->json($requirements);
    }

    public function upload(Request $request, string $id)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf',
        ]);

        $student = $request->user()->student;
        $requirement = InternshipRequirement::where('id', $id)
            ->where('student_id', $student->id)
            ->forCurrentInternship()
            ->whereHas('definition', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $requirement) {
            return response()->json(['message' => 'Requirement not found'], 404);
        }

        $file = $request->file('file');
        $path = $file->store('requirements/'.$student->id, 'local');
        try {
            $oldPath = DB::transaction(function () use ($requirement, $student, $path) {
                StudentInternship::query()->whereKey($requirement->student_internship_id)->lockForUpdate()->firstOrFail();
                $locked = InternshipRequirement::query()->whereKey($requirement->id)->lockForUpdate()->firstOrFail();
                abort_if($this->folderLocked($locked), 422, 'This student folder is locked during final approval and VPAA review.');
                abort_if($locked->isConsent() && ApprovalRecord::query()
                    ->where('subject_type', ApprovalRecord::TYPE_DOCUMENT)
                    ->where('subject_id', $locked->id)
                    ->whereIn('status', ['pending', 'approved'])->exists(),
                    422, 'This consent is already under final approval and cannot be replaced.');

                $oldPath = $this->privateStoragePath($locked->file_path);
                $locked->update([
                    'file_path' => $path,
                    'status' => 'pending',
                    'feedback' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                if ($locked->isConsent()) {
                    $student->update(['consent_status' => 'pending']);
                }
                return $oldPath;
            });
        } catch (\Throwable $exception) {
            $this->privateDisk()->delete($path);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            $this->privateDisk()->delete($oldPath);
        }

        return response()->json([
            'message' => 'File uploaded successfully',
            'requirement' => $requirement->refresh(),
        ]);
    }

    public function download(string $id, Request $request)
    {
        $student = $request->user()->student;
        $requirement = InternshipRequirement::where('id', $id)
            ->where('student_id', $student->id)
            ->forCurrentInternship()
            ->first();

        $path = $this->privateStoragePath($requirement?->file_path);
        if (! $requirement || ! $path || ! $this->privateDisk()->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return $this->privateDisk()->download($path);
    }

    public function removeFile(string $id, Request $request)
    {
        $student = $request->user()->student;
        $requirement = InternshipRequirement::where('id', $id)
            ->where('student_id', $student->id)
            ->forCurrentInternship()
            ->whereHas('definition', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $requirement || ! $requirement->file_path) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $path = DB::transaction(function () use ($requirement, $student) {
            StudentInternship::query()->whereKey($requirement->student_internship_id)->lockForUpdate()->firstOrFail();
            $locked = InternshipRequirement::query()->whereKey($requirement->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->file_path, 404, 'Submission not found.');
            abort_if($this->folderLocked($locked), 422, 'This student folder is locked during final approval and VPAA review.');
            abort_if(ApprovalRecord::query()
                ->where('subject_type', ApprovalRecord::TYPE_DOCUMENT)
                ->where('subject_id', $locked->id)
                ->whereIn('status', ['pending', 'approved'])->exists(),
                422, 'This submission is already under approval and cannot be removed.');

            $path = $this->privateStoragePath($locked->file_path);
            $locked->update([
                'file_path' => null,
                'status' => 'pending',
                'feedback' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
            if ($locked->isConsent()) {
                $student->update(['consent_status' => 'pending']);
            }
            return $path;
        });

        if ($path) {
            $this->privateDisk()->delete($path);
        }

        return response()->json(['message' => 'Submission removed successfully', 'requirement' => $requirement->refresh()]);
    }

    private function folderLocked(InternshipRequirement $requirement): bool
    {
        return StudentDocumentApproval::query()
            ->where('student_internship_id', $requirement->student_internship_id)
            ->whereIn('status', [StudentDocumentApproval::FOR_DEAN_REVIEW, StudentDocumentApproval::FOR_REVIEW,
                StudentDocumentApproval::REVIEWED, StudentDocumentApproval::FINAL_APPROVED])
            ->exists();
    }
}
