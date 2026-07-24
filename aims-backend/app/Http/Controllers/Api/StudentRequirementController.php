<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternshipRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentRequirementController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        InternshipRequirement::ensureForStudent($student->id);

        $requirements = InternshipRequirement::where('student_id', $student->id)
            ->get();

        return response()->json(
            collect(InternshipRequirement::OFFICIAL_REQUIREMENTS)
                ->map(fn ($name) => $requirements->firstWhere('requirement_name', $name))
                ->filter()
                ->values()
        );
    }

    public function upload(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        $student = $request->user()->student;

        $requirement = InternshipRequirement::where('id', $id)
            ->where('student_id', $student->id)
            ->first();

        if (! $requirement) {
            return response()->json(['message' => 'Requirement not found'], 404);
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileType = in_array($extension, ['pdf']) ? 'pdf' : (in_array($extension, ['jpg', 'jpeg', 'png']) ? 'image' : 'document');

        $oldPath = $requirement->file_path;
        $path = $file->store('requirements/'.$student->id, 'public');

        $requirement->update([
            'file_path' => $path,
            'file_type' => $fileType,
            'status' => 'pending',
            'feedback' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'File uploaded successfully',
            'requirement' => $requirement,
        ]);
    }

    public function download($id, Request $request)
    {
        $student = $request->user()->student;

        $requirement = InternshipRequirement::where('id', $id)
            ->where('student_id', $student->id)
            ->first();

        if (!$requirement || !$requirement->file_path) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($requirement->file_path);
    }
}
