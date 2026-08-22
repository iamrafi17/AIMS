<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Models\TravelLog;
use App\Models\TravelReport;
use App\Models\User;
use App\Support\ProgramAccess;
use App\Support\TravelWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramHeadTravelController extends Controller
{
    public function index(Request $request)
    {
        $query = TravelLog::query()->with(TravelWorkflow::relations());
        if ($request->user()->role === 'program_head') {
            $programId = ProgramAccess::programId($request->user());
            $query->whereHas('participants', fn ($participant) => $participant->where('program_id', $programId));
            $photoBase = '/program-head/travel';
        } else {
            $query->whereHas('report', fn ($report) => $report->whereIn('status', ['pending_vpaa', 'final_validated']));
            $photoBase = '/vpaa/travel';
        }

        $sessions = $query->latest('start_time')->get();
        $reports = $sessions->pluck('report')->filter();

        return response()->json([
            'role' => $request->user()->role,
            'summary' => [
                'active_sessions' => $sessions->whereIn('status', ['active', 'traveling', 'arrived'])->count(),
                'completed_sessions' => $sessions->where('status', 'completed')->count(),
                'pending_validation' => $request->user()->role === 'vpaa'
                    ? $reports->where('status', 'pending_vpaa')->count()
                    : $reports->where('status', 'pending_program_head')->count(),
                'validated_reports' => $request->user()->role === 'vpaa'
                    ? $reports->where('status', 'final_validated')->count()
                    : $reports->whereIn('status', ['pending_vpaa', 'final_validated'])->count(),
            ],
            'sessions' => $sessions->map(fn (TravelLog $session) => TravelWorkflow::sessionPayload($session, $photoBase))->values(),
        ]);
    }

    public function reviewReport(Request $request, TravelReport $report)
    {
        abort_unless($request->user()->role === 'program_head', 403);
        $report->loadMissing('travelLog.participants');
        ProgramAccess::authorizeTravel($request->user(), $report->travelLog);
        abort_unless($report->status === 'pending_program_head', 422, 'Only reports submitted by the Coordinator can be reviewed.');
        $data = $request->validate([
            'decision' => ['prohibited'],
            'reason' => ['prohibited'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $report, $data) {
            $locked = TravelReport::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless($locked->status === 'pending_program_head', 422, 'This report is no longer pending Program Head validation.');
            $locked->update([
                'status' => 'pending_vpaa',
                'program_head_validated_at' => now(),
                'forwarded_at' => now(),
            ]);
            $locked->validations()->create([
                'reviewer_id' => $request->user()->id,
                'reviewer_role' => 'program_head',
                'action' => 'validated_and_forwarded',
                'comments' => $data['comments'] ?? null,
                'reviewed_at' => now(),
                'report_version' => $locked->version,
            ]);
        });

        SystemNotification::sendToUser(
            $report->coordinator_id,
            'Travel report validated and forwarded',
            $report->report_number.' was validated by the Program Head and forwarded to the VPAA.',
            'travel',
            '/coordinator/travel',
        );

        User::query()->where('role', 'vpaa')->where('is_active', true)->pluck('id')->each(
            fn (int $userId) => SystemNotification::sendToUser($userId, 'Travel report ready for final validation', $report->report_number.' was forwarded by the Program Head.', 'travel', '/vpaa/travel')
        );

        return response()->json([
            'message' => 'Report validated and automatically forwarded to the VPAA.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function finalReviewReport(Request $request, TravelReport $report)
    {
        abort_unless($request->user()->role === 'vpaa', 403);
        abort_unless($report->status === 'pending_vpaa', 422, 'Only Program Head-forwarded reports can receive final validation.');
        $data = $request->validate([
            'decision' => ['prohibited'],
            'reason' => ['prohibited'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $report, $data) {
            $locked = TravelReport::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless($locked->status === 'pending_vpaa', 422, 'This report is no longer pending VPAA validation.');
            $locked->update([
                'status' => 'final_validated',
                'final_validated_at' => now(),
            ]);
            $locked->validations()->create([
                'reviewer_id' => $request->user()->id,
                'reviewer_role' => 'vpaa',
                'action' => 'final_validated',
                'comments' => $data['comments'] ?? null,
                'reviewed_at' => now(),
                'report_version' => $locked->version,
            ]);
        });

        $report->loadMissing('travelLog.participants.user');
        SystemNotification::sendToUser(
            $report->coordinator_id,
            'Travel report final validated',
            $report->report_number.' completed final VPAA validation.',
            'travel',
            '/coordinator/travel',
        );
        SystemNotification::sendToUser($report->travelLog->participants->first()?->user_id, 'Travel report final validated', 'Your travel report '.$report->report_number.' completed final validation.', 'travel', '/student/travel');

        return response()->json([
            'message' => 'Travel report received final VPAA validation and is now read-only.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function arrivalPhoto(Request $request, TravelLog $travel)
    {
        if ($request->user()->role === 'program_head') {
            ProgramAccess::authorizeTravel($request->user(), $travel);
        } else {
            abort_unless($travel->report()->whereIn('status', ['pending_vpaa', 'final_validated'])->exists(), 404);
        }
        $path = $this->privateStoragePath($travel->arrival_photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Arrival photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }
}
