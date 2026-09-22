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

class CoordinatorTravelController extends Controller
{
    public function index(Request $request)
    {
        ProgramAccess::programId($request->user());
        $sessions = $this->sessions($request)
            ->with(TravelWorkflow::relations())
            ->latest('start_time')
            ->get();
        $reports = $sessions->pluck('report')->filter();

        return response()->json([
            'summary' => [
                'active_sessions' => $sessions->whereIn('status', ['active', 'traveling', 'arrived'])->count(),
                'completed_sessions' => $sessions->where('status', 'completed')->count(),
                'pending_reports' => $reports->whereIn('status', ['pending_program_head', 'pending_vpaa'])->count(),
                'validated_reports' => $reports->where('status', 'final_validated')->count(),
            ],
            'sessions' => $sessions->map(fn (TravelLog $session) => TravelWorkflow::sessionPayload($session, '/coordinator/travel'))->values(),
        ]);
    }

    public function show(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);

        return response()->json([
            'session' => TravelWorkflow::sessionPayload($travel->load(TravelWorkflow::relations()), '/coordinator/travel'),
        ]);
    }

    public function generateReport(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);
        abort_unless($travel->status === 'completed', 422, 'A report can only be generated after the Student completes the travel session.');

        $report = DB::transaction(function () use ($request, $travel) {
            $travel = TravelLog::query()->lockForUpdate()->findOrFail($travel->id);

            return TravelReport::firstOrCreate(
                ['travel_log_id' => $travel->id],
                [
                    'coordinator_id' => $request->user()->id,
                    'report_number' => 'TMR-'.$travel->session_code,
                    'status' => 'draft',
                    'version' => 1,
                    'generated_at' => now(),
                ],
            );
        });

        return response()->json([
            'message' => $report->wasRecentlyCreated ? 'Travel report generated from the saved Student evidence.' : 'The existing travel report was loaded.',
            'session' => TravelWorkflow::sessionPayload($travel->fresh()->load(TravelWorkflow::relations()), '/coordinator/travel'),
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    public function updateReport(Request $request, TravelReport $report)
    {
        $report->loadMissing('travelLog');
        TravelWorkflow::authorizeCoordinator($request->user(), $report->travelLog);
        abort_unless(in_array($report->status, TravelReport::EDITABLE_STATUSES, true), 422, 'This report is locked after submission.');
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:5000']]);
        $report->update(['remarks' => $data['remarks'] ?? null]);

        return response()->json([
            'message' => 'Coordinator remarks saved.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function submitReport(Request $request, TravelReport $report)
    {
        $report->loadMissing('travelLog.participants');
        TravelWorkflow::authorizeCoordinator($request->user(), $report->travelLog);
        abort_unless(in_array($report->status, TravelReport::EDITABLE_STATUSES, true), 422, 'Only a draft report can be submitted.');

        DB::transaction(function () use ($request, $report) {
            $locked = TravelReport::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(in_array($locked->status, TravelReport::EDITABLE_STATUSES, true), 422, 'This report is no longer editable.');
            $locked->update([
                'status' => 'pending_program_head',
                'submitted_at' => now(),
                'program_head_validated_at' => null,
                'forwarded_at' => null,
                'final_validated_at' => null,
            ]);
            $locked->validations()->create([
                'reviewer_id' => $request->user()->id,
                'reviewer_role' => 'coordinator',
                'action' => 'submitted',
                'reviewed_at' => now(),
                'report_version' => $locked->version,
            ]);
        });

        $student = $report->travelLog->participants->first();
        User::query()->where('role', 'program_head')->where('is_active', true)
            ->where('college_id', $student?->college_id)->where('program_id', $student?->program_id)
            ->pluck('id')->each(fn (int $userId) => SystemNotification::sendToUser(
                $userId,
                'New travel report submitted',
                $report->report_number.' is ready for Program Head validation.',
                'travel',
                '/program-head/travel',
            ));

        return response()->json([
            'message' => 'Report submitted to the Program Head.',
            'report' => TravelWorkflow::reportPayload($report->fresh()),
        ]);
    }

    public function arrivalPhoto(Request $request, TravelLog $travel)
    {
        TravelWorkflow::authorizeCoordinator($request->user(), $travel);
        $path = $this->privateStoragePath($travel->arrival_photo_path);
        abort_unless($path && $this->privateDisk()->exists($path), 404, 'Arrival photo not found.');

        return response()->file($this->privateDisk()->path($path));
    }

    private function sessions(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());

        return TravelLog::query()
            ->where('coordinator_id', $request->user()->id)
            ->whereHas('participants', fn ($query) => $query->forProgram($programId));
    }
}
