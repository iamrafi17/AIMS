<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\User;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramHeadDeploymentController extends Controller
{
    public function review(Request $request, ApprovalRecord $approval)
    {
        abort_unless($approval->subject_type === ApprovalRecord::TYPE_DEPLOYMENT, 404);
        $student = Student::findOrFail($approval->subject_id);
        ProgramAccess::authorizeStudent($request->user(), $student);
        abort_unless($approval->submitter?->role === 'coordinator', 403, 'Only Coordinator deployment submissions can be reviewed here.');

        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'This deployment request has already been reviewed.']);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:forwarded,returned'],
            'remarks' => ['nullable', 'required_if:decision,returned', 'string', 'max:2000'],
        ]);

        $deanApproval = DB::transaction(function () use ($approval, $request, $student, $validated) {
            $locked = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
            abort_unless($locked->status === 'pending', 422, 'This deployment request is no longer pending.');

            $locked->update([
                'status' => $validated['decision'] === 'forwarded' ? 'approved' : 'rejected',
                'remarks' => $validated['remarks'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            if ($validated['decision'] === 'returned') {
                $student->update(['schedule_status' => 'rejected']);

                return null;
            }

            $student->update(['schedule_status' => 'pending']);

            return ApprovalRecord::submit(ApprovalRecord::TYPE_DEPLOYMENT, $student->id, $request->user()->id);
        });

        if ($deanApproval) {
            User::query()->forRole('dean')->where('is_active', true)
                ->whereHas('employeeProfile', fn ($profile) => $profile->where('college_id', $student->college_id))
                ->pluck('id')->each(fn (int $userId) => SystemNotification::sendToUser($userId, 'Deployment request forwarded', $student->student_id.' deployment awaits final validation.', 'approval', '/dean/approvals'));
        } else {
            SystemNotification::sendToUser($approval->submitted_by, 'Deployment request returned', $student->student_id.' deployment requires revision: '.$validated['remarks'], 'approval', '/coordinator/htes');
        }

        return response()->json([
            'message' => $validated['decision'] === 'forwarded' ? 'Deployment request forwarded to the Dean.' : 'Deployment request returned to the Coordinator.',
        ]);
    }
}
