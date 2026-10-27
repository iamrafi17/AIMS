<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Models\TravelCheckpoint;
use App\Models\TravelLog;
use App\Models\TravelReport;
use App\Models\User;
use App\Support\DeanAccess;
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
            $query->whereHas('participants', fn ($participant) => $participant->forProgram($programId));
            $photoBase = '/program-head/travel';
        } elseif ($request->user()->role === 'dean') {
            $collegeId = DeanAccess::collegeId($request->user());
            $query->whereHas('participants')->whereDoesntHave('participants', fn ($participant) => $participant->whereDoesntHave('currentInternship.academicSection.program', fn ($program) => $program->where('college_id', $collegeId)))
                ->whereHas('report', fn ($report) => $report->whereIn('status', ['pending_vpaa', 'final_validated']));
            $photoBase = '/dean/travel';
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
                'pending_validation' => in_array($request->user()->role, ['vpaa', 'dean'], true)
                    ? $reports->where('status', 'pending_vpaa')->count()
                    : $reports->where('status', 'pending_program_head')->count(),
                'validated_reports' => in_array($request->user()->role, ['vpaa', 'dean'], true)
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
            $report->report_number.' was validated by the Program Head and forwarded to the Dean.',
            'travel',
            '/coordinator/travel',
        );

        $report->loadMissing('travelLog.participants');
        $collegeId = $report->travelLog->participants->first()?->college_id;
        User::query()->forRole('dean')->where('is_active', true)->whereHas('employeeProfile', fn ($profile) => $profile->where('college_id', $collegeId))->pluck('id')->each(
            fn (int $userId) => SystemNotification::sendToUser($userId, 'Travel report ready for final validation', $report->report_number.' was forwarded by the Program Head.', 'travel', '/dean/travel')
        );

        return response()->json([
            'message' => 'Report validated and automatically forwarded to the Dean.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function finalReviewReport(Request $request, TravelReport $report)
    {
        abort_unless($request->user()->role === 'dean', 403);
        DeanAccess::travel($request->user(), $report->travelLog);
        abort_unless($report->status === 'pending_vpaa', 422, 'Only Program Head-forwarded reports can receive final validation.');
        $data = $request->validate([
            'decision' => ['nullable', 'in:approved,returned'],
            'reason' => ['required_if:decision,returned', 'nullable', 'string', 'max:2000'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $report, $data) {
            $locked = TravelReport::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless($locked->status === 'pending_vpaa', 422, 'This report is no longer pending Dean validation.');
            $reviewedVersion = $locked->version;
            $returned = ($data['decision'] ?? 'approved') === 'returned';
            $locked->update([
                'status' => $returned ? 'draft' : 'final_validated',
                'final_validated_at' => $returned ? null : now(),
                'program_head_validated_at' => $returned ? null : $locked->program_head_validated_at,
                'forwarded_at' => $returned ? null : $locked->forwarded_at,
                'version' => $returned ? $locked->version + 1 : $locked->version,
            ]);
            $locked->validations()->create([
                'reviewer_id' => $request->user()->id,
                'reviewer_role' => 'dean',
                'action' => $returned ? 'returned' : 'final_validated',
                'reason' => $data['reason'] ?? null,
                'comments' => $data['comments'] ?? null,
                'reviewed_at' => now(),
                'report_version' => $reviewedVersion,
            ]);
        });

        $report->loadMissing('travelLog.participants.user');
        $returned = ($data['decision'] ?? 'approved') === 'returned';
        SystemNotification::sendToUser(
            $report->coordinator_id,
            $returned ? 'Travel report returned by Dean' : 'Travel report final validated',
            $returned ? $report->report_number.' requires revision: '.$data['reason'] : $report->report_number.' completed final Dean validation.',
            'travel',
            '/coordinator/travel',
        );
        if (! $returned) {
            SystemNotification::sendToUser($report->travelLog->participants->first()?->user_id, 'Travel report final validated', 'Your travel report '.$report->report_number.' completed final validation.', 'travel', '/student/travel');
        }

        return response()->json([
            'message' => $returned ? 'Travel report returned to the Coordinator for revision.' : 'Travel report received final Dean validation and is now read-only.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function arrivalPhoto(Request $request, TravelLog $travel)
    {
        $this->authorizePhotoViewer($request, $travel);
        $path = $this->privateStoragePath($travel->arrival_photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Arrival photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    public function checkpointPhoto(Request $request, TravelLog $travel, TravelCheckpoint $checkpoint)
    {
        $this->authorizePhotoViewer($request, $travel);
        abort_unless((int) $checkpoint->travel_log_id === (int) $travel->id, 404);
        $path = $this->privateStoragePath($checkpoint->photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Checkpoint photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    private function authorizePhotoViewer(Request $request, TravelLog $travel): void
    {
        if ($request->user()->role === 'program_head') {
            ProgramAccess::authorizeTravel($request->user(), $travel);
        } elseif ($request->user()->role === 'dean') {
            DeanAccess::travel($request->user(), $travel);
        } else {
            abort_unless($travel->report()->whereIn('status', ['pending_vpaa', 'final_validated'])->exists(), 404);
        }
    }
}
