<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\MOA;
use App\Models\SystemNotification;
use App\Support\DeanAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VPAAMOAController extends Controller
{
    public function index(Request $request)
    {
        $collegeId = $request->user()->role === 'dean' ? DeanAccess::collegeId($request->user()) : null;
        $approvals = ApprovalRecord::with(['submitter', 'decisionMaker'])
            ->where('subject_type', ApprovalRecord::TYPE_MOA)
            ->orderByDesc('id')->get()->unique('subject_id')->values();
        $moas = MOA::with(['hte', 'program.college', 'approver', 'programReviewer', 'deanEndorser', 'vpaaReviewer'])
            ->when($collegeId, fn ($query) => $query->whereHas('program', fn ($program) => $program->where('college_id', $collegeId)))
            ->whereIn('id', $approvals->pluck('subject_id'))->get()->keyBy('id');
        $items = $approvals->map(fn (ApprovalRecord $approval) => $moas->has($approval->subject_id)
            && $moas[$approval->subject_id]->program_status === 'endorsed'
            ? $this->payload($approval, $moas[$approval->subject_id])
            : null)->filter()->values();
        $isDean = $request->user()->role === 'dean';
        $pending = $items->filter(fn ($item) => $isDean
            ? $item['approval_status'] === 'pending' && $item['status'] === 'pending'
            : $item['approval_status'] === 'approved' && $item['status'] === 'approved'
                && $item['dean_approved_by'] && ! $item['vpaa_reviewed_at'])->values();
        $history = $items->filter(fn ($item) => $isDean
            ? in_array($item['approval_status'], ['approved', 'returned'], true)
            : $item['approval_status'] === 'approved' && (bool) $item['vpaa_reviewed_at'])->values();

        return response()->json([
            'pending' => $pending,
            'history' => $history,
            'summary' => [
                'pending' => $pending->count(),
                'approved' => $items->where('approval_status', 'approved')->count(),
                'reviewed' => $items->filter(fn ($item) => (bool) $item['vpaa_reviewed_at'])->count(),
                'returned' => $history->where('approval_status', 'returned')->count(),
                'expiring' => $items->where('approval_status', 'approved')->filter(fn ($item) => $item['days_remaining'] >= 0 && $item['days_remaining'] <= 60)->count(),
                'expired' => $items->filter(fn ($item) => $item['validity'] === 'expired')->count(),
            ],
        ]);
    }

    public function review(Request $request, ApprovalRecord $approval)
    {
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_MOA, 404);
        $moa = MOA::findOrFail($approval->subject_id);
        DeanAccess::moa($request->user(), $moa);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,returned'],
            'remarks' => ['nullable', 'required_if:decision,returned', 'string', 'max:2000'],
        ]);
        $decision = $data['decision'];

        DB::transaction(function () use ($approval, $request, $data, $decision, $moa) {
            $approval = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
            $moa = MOA::query()->lockForUpdate()->findOrFail($moa->id);
            abort_unless($approval->status === 'pending' && $moa->program_status === 'endorsed' && $moa->status === 'pending',
                422, 'This MOA is no longer awaiting Dean final approval.');
            abort_if($decision !== 'returned' && $moa->expiration_date->lt(today()), 422, 'This MOA has expired. A renewed MOA is required.');
            $moa->update([
                'status' => $decision,
                'dean_endorsed_by' => $decision === 'approved' ? $request->user()->id : null,
                'dean_endorsed_at' => $decision === 'approved' ? now() : null,
                'dean_remarks' => $data['remarks'] ?? null,
                'approved_by' => $decision === 'approved' ? $request->user()->id : null,
                'approved_at' => $decision === 'approved' ? now() : null,
            ]);
            $approval->update(['status' => $decision, 'remarks' => $data['remarks'] ?? null,
                'decided_by' => $request->user()->id, 'decided_at' => now()]);
        });
        if ($decision === 'approved') {
            SystemNotification::sendToRole('vpaa', 'MOA ready for review', 'A Dean finally approved an MOA for VPAA acknowledgment.', 'approval', '/vpaa/moas');
        }
        SystemNotification::sendToUser($approval->submitted_by, 'Dean MOA decision recorded',
            $decision === 'approved' ? 'The MOA received final Dean approval.' : 'The Dean returned the MOA: '.$data['remarks'],
            'approval', '/program-head/documents');

        return response()->json(['message' => $decision === 'approved'
            ? 'MOA finally approved and sent to VPAA for review.' : 'MOA returned to the Program Head.']);
    }

    public function markReviewed(Request $request, ApprovalRecord $approval)
    {
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_MOA, 404);
        DB::transaction(function () use ($approval, $request): void {
            $locked = ApprovalRecord::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $moa = MOA::query()->whereKey($locked->subject_id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'approved' && $moa->status === 'approved'
                && $moa->approver?->role === 'dean' && ! $moa->vpaa_reviewed_at,
                422, 'Dean final approval is required before VPAA review, or this MOA was already reviewed.');
            $moa->update(['vpaa_reviewed_by' => $request->user()->id, 'vpaa_reviewed_at' => now()]);
        });

        SystemNotification::sendToUser($approval->submitted_by, 'VPAA MOA review recorded',
            'The Dean-approved MOA was marked reviewed by VPAA.', 'approval', '/program-head/documents');
        return response()->json(['message' => 'MOA marked as reviewed.']);
    }

    public function download(Request $request, ApprovalRecord $approval)
    {
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_MOA, 404);
        $moa = MOA::findOrFail($approval->subject_id);
        if ($request->user()->role === 'dean') {
            DeanAccess::moa($request->user(), $moa);
        } else {
            abort_unless($moa->status === 'approved' && $approval->status === 'approved', 404);
        }
        abort_unless($moa->program_status === 'endorsed', 404);
        $path = $this->privateStoragePath($moa->file_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404);

        return $this->privateDisk()->download($path);
    }

    private function payload(ApprovalRecord $approval, MOA $moa): array
    {
        return [
            'approval_id' => $approval->id,
            'moa_id' => $moa->id,
            'hte' => $moa->hte?->name,
            'program' => $moa->program?->code,
            'program_id' => $moa->program_id,
            'hte_address' => $moa->hte?->address,
            'college' => $moa->college?->name,
            'college_code' => $moa->college?->code,
            'effective_date' => $moa->effective_date?->toDateString(),
            'expiration_date' => $moa->expiration_date?->toDateString(),
            'days_remaining' => now()->startOfDay()->diffInDays($moa->expiration_date, false),
            'validity' => $moa->expiration_date->lt(today()) ? 'expired' : ($moa->effective_date->gt(today()) ? 'upcoming' : ($moa->expiration_date->lte(today()->addDays(60)) ? 'expiring soon' : 'valid')),
            'program_status' => $moa->program_status,
            'status' => $moa->status,
            'dean_endorsed_by' => $moa->deanEndorser?->name,
            'dean_endorsed_at' => $moa->dean_endorsed_at?->toIso8601String(),
            'dean_remarks' => $moa->dean_remarks,
            'dean_approved_by' => $moa->approver?->role === 'dean' ? $moa->approver->name : null,
            'dean_approved_at' => $moa->approver?->role === 'dean' ? $moa->approved_at?->toIso8601String() : null,
            'vpaa_reviewed_by' => $moa->vpaaReviewer?->name,
            'vpaa_reviewed_at' => $moa->vpaa_reviewed_at?->toIso8601String(),
            'endorsed_by' => $approval->submitter?->name ?? $moa->programReviewer?->name,
            'endorsed_at' => $approval->created_at?->toIso8601String(),
            'approval_status' => $approval->status,
            'remarks' => $approval->remarks,
            'decided_by' => $approval->decisionMaker?->name,
            'decided_at' => $approval->decided_at?->toIso8601String(),
        ];
    }
}
