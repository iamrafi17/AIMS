<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramHeadDocumentController extends Controller
{
    public function index()
    {
        $requirements = InternshipRequirement::with([
            'student.user',
            'student.program',
            'student.college',
            'student.hte',
            'reviewer',
        ])
            ->whereNotNull('file_path')
            ->latest('updated_at')
            ->get();

        $moas = MOA::with(['hte', 'college', 'approver'])
            ->latest('updated_at')
            ->get();

        $today = now()->startOfDay();

        return response()->json([
            'summary' => [
                'submitted_requirements' => $requirements->count(),
                'pending_requirements' => $requirements->where('status', 'pending')->count(),
                'approved_requirements' => $requirements->where('status', 'approved')->count(),
                'rejected_requirements' => $requirements->where('status', 'rejected')->count(),
                'total_moas' => $moas->count(),
                'pending_moas' => $moas->where('status', 'pending')->count(),
                'approved_moas' => $moas->where('status', 'approved')->count(),
                'expiring_moas' => $moas->filter(fn (MOA $moa) => $moa->status === 'approved'
                    && $moa->expiration_date->gte($today)
                    && $moa->expiration_date->lte($today->copy()->addDays(60)))->count(),
            ],
            'requirements' => $requirements->map(fn (InternshipRequirement $requirement) => $this->requirementPayload($requirement))->values(),
            'moas' => $moas->map(fn (MOA $moa) => $this->moaPayload($moa, $today))->values(),
        ]);
    }

    public function reviewRequirement(Request $request, InternshipRequirement $requirement)
    {
        if (! $requirement->file_path) {
            return response()->json(['message' => 'The student has not uploaded this requirement yet.'], 422);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'feedback' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        $requirement->update([
            'status' => $validated['decision'],
            'feedback' => $validated['feedback'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Requirement '.$validated['decision'].'.',
            'requirement' => $this->requirementPayload($requirement->fresh([
                'student.user',
                'student.program',
                'student.college',
                'student.hte',
                'reviewer',
            ])),
        ]);
    }

    public function downloadRequirement(InternshipRequirement $requirement)
    {
        if (! $requirement->file_path || ! Storage::disk('public')->exists($requirement->file_path)) {
            return response()->json(['message' => 'Requirement file not found.'], 404);
        }

        return Storage::disk('public')->download($requirement->file_path);
    }

    public function reviewMoa(Request $request, MOA $moa)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ]);

        $moa->update([
            'status' => $validated['decision'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'MOA '.$validated['decision'].'.',
            'moa' => $this->moaPayload($moa->fresh(['hte', 'college', 'approver']), now()->startOfDay()),
        ]);
    }

    public function downloadMoa(MOA $moa)
    {
        if (! Storage::disk('public')->exists($moa->file_path)) {
            return response()->json(['message' => 'MOA file not found.'], 404);
        }

        return Storage::disk('public')->download($moa->file_path);
    }

    private function requirementPayload(InternshipRequirement $requirement): array
    {
        return [
            'id' => $requirement->id,
            'requirement_name' => $requirement->requirement_name,
            'file_path' => $requirement->file_path,
            'file_type' => $requirement->file_type,
            'status' => $requirement->status,
            'feedback' => $requirement->feedback,
            'submitted_at' => $requirement->updated_at?->toIso8601String(),
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

    private function moaPayload(MOA $moa, $today): array
    {
        $expired = $moa->expiration_date->lt($today);

        return [
            'id' => $moa->id,
            'hte' => $moa->hte?->name,
            'college' => $moa->college?->code,
            'file_path' => $moa->file_path,
            'effective_date' => $moa->effective_date->toDateString(),
            'expiration_date' => $moa->expiration_date->toDateString(),
            'status' => $moa->status,
            'computed_status' => $expired ? 'expired' : $moa->status,
            'days_remaining' => (int) $today->diffInDays($moa->expiration_date, false),
            'reviewed_at' => $moa->approved_at?->toIso8601String(),
            'reviewer' => $moa->approver?->name,
            'updated_at' => $moa->updated_at?->toIso8601String(),
        ];
    }
}
