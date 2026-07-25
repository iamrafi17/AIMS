<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\MOA;
use App\Models\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VPAAMOAController extends Controller
{
    public function index()
    {
        $approvals = ApprovalRecord::with(['submitter', 'decisionMaker'])
            ->where('subject_type', ApprovalRecord::TYPE_MOA)
            ->latest()->get();
        $moas = MOA::with(['hte', 'college', 'approver', 'programReviewer'])
            ->whereIn('id', $approvals->pluck('subject_id'))->get()->keyBy('id');
        $items = $approvals->map(fn (ApprovalRecord $approval) => $moas->has($approval->subject_id)
            ? $this->payload($approval, $moas[$approval->subject_id])
            : null)->filter()->values();

        return response()->json([
            'pending' => $items->where('approval_status', 'pending')->values(),
            'history' => $items->whereIn('approval_status', ['approved', 'rejected'])->values(),
            'summary' => [
                'pending' => $items->where('approval_status', 'pending')->count(),
                'approved' => $items->where('approval_status', 'approved')->count(),
                'rejected' => $items->where('approval_status', 'rejected')->count(),
                'expiring' => $items->where('approval_status', 'approved')->filter(fn ($item) => $item['days_remaining'] >= 0 && $item['days_remaining'] <= 60)->count(),
            ],
        ]);
    }

    public function review(Request $request, ApprovalRecord $approval)
    {
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_MOA, 404);
        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'This MOA has already received a final decision.']);
        }
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'remarks' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($approval, $request, $data) {
            MOA::findOrFail($approval->subject_id)->update([
                'status' => $data['decision'],
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $approval->update([
                'status' => $data['decision'],
                'remarks' => $data['remarks'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
        });
        SystemNotification::sendToUser($approval->submitted_by, 'MOA final decision recorded', 'The endorsed MOA was '.$data['decision'].' by the VPAA.', 'approval', '/program-head/documents');

        return response()->json(['message' => 'MOA '.$data['decision'].' successfully.']);
    }

    public function download(ApprovalRecord $approval)
    {
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_MOA, 404);
        $moa = MOA::findOrFail($approval->subject_id);
        abort_unless(Storage::disk('public')->exists($moa->file_path), 404);

        return Storage::disk('public')->download($moa->file_path);
    }

    private function payload(ApprovalRecord $approval, MOA $moa): array
    {
        return [
            'approval_id' => $approval->id,
            'moa_id' => $moa->id,
            'hte' => $moa->hte?->name,
            'hte_address' => $moa->hte?->address,
            'college' => $moa->college?->name,
            'college_code' => $moa->college?->code,
            'effective_date' => $moa->effective_date?->toDateString(),
            'expiration_date' => $moa->expiration_date?->toDateString(),
            'days_remaining' => now()->startOfDay()->diffInDays($moa->expiration_date, false),
            'program_status' => $moa->program_status,
            'endorsed_by' => $approval->submitter?->name ?? $moa->programReviewer?->name,
            'endorsed_at' => $approval->created_at?->toIso8601String(),
            'approval_status' => $approval->status,
            'remarks' => $approval->remarks,
            'decided_by' => $approval->decisionMaker?->name,
            'decided_at' => $approval->decided_at?->toIso8601String(),
        ];
    }
}
