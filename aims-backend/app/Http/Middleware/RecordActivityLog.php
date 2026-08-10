<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AdminSystemController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CoordinatorAttendanceController;
use App\Http\Controllers\Api\CoordinatorHTEController;
use App\Http\Controllers\Api\CoordinatorRequirementController;
use App\Http\Controllers\Api\CoordinatorStudentController;
use App\Http\Controllers\Api\CoordinatorTravelController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramHeadDocumentController;
use App\Http\Controllers\Api\ProgramHeadTravelController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StudentRequirementController;
use App\Http\Controllers\Api\SupervisorManagementController;
use App\Http\Controllers\Api\VPAAApprovalController;
use App\Http\Controllers\Api\VPAAMOAController;
use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordActivityLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || $response->getStatusCode() >= 400) {
            return $response;
        }

        $activity = $this->resolveActivity($request, $response);

        if ($activity) {
            ActivityLog::record($request->user(), $activity[0], $activity[1]);
        }

        return $response;
    }

    private function resolveActivity(Request $request, Response $response): ?array
    {
        return match ($request->route()?->getActionName()) {
            AuthController::class.'@logout' => ['Authentication', 'Logged out.'],
            ProfileController::class.'@update',
            ProfileController::class.'@updateAccount',
            ProfileController::class.'@updateStudentInformation',
            ProfileController::class.'@updateContact',
            ProfileController::class.'@changePassword',
            ProfileController::class.'@uploadAvatar',
            ProfileController::class.'@removeAvatar' => ['Profile', 'Updated profile.'],

            StudentAttendanceController::class.'@saveEntry' => ['Accomplishment Report', 'Submitted accomplishment report.'],
            StudentAttendanceController::class.'@smartLog' => $this->smartClockActivity($request, $response),
            StudentAttendanceController::class.'@clockIn' => ['Time In', 'Recorded time in.'],
            StudentAttendanceController::class.'@clockOut' => ['Time Out', 'Recorded time out.'],
            StudentRequirementController::class.'@upload' => ['Internship Requirement', 'Uploaded an internship requirement.'],
            CoordinatorAttendanceController::class.'@update' => ['Attendance Review', 'Updated a student attendance record.'],
            CoordinatorAttendanceController::class.'@verify' => ['Attendance Review', 'Reviewed student attendance.'],
            CoordinatorAttendanceController::class.'@reviewJournal' => ['Attendance Review', 'Reviewed a student accomplishment report.'],
            CoordinatorHTEController::class.'@store' => ['HTE Management', 'Added an HTE.'],
            CoordinatorHTEController::class.'@update' => ['HTE Management', 'Updated an HTE.'],
            CoordinatorHTEController::class.'@destroy' => ['HTE Management', 'Deactivated an HTE.'],
            CoordinatorHTEController::class.'@deploy' => ['Student Assignment', 'Assigned a student to an HTE.'],
            CoordinatorStudentController::class.'@store' => ['Student Management', 'Added a student record.'],
            CoordinatorStudentController::class.'@update' => ['Student Management', 'Updated student information.'],
            CoordinatorStudentController::class.'@destroy',
            CoordinatorStudentController::class.'@deactivate' => ['Student Management', 'Deactivated a student record.'],
            CoordinatorStudentController::class.'@approveRegistration' => ['Registration', 'Approved a student registration.'],
            CoordinatorStudentController::class.'@rejectRegistration' => ['Registration', 'Rejected a student registration.'],
            CoordinatorStudentController::class.'@reviewRequirement' => [
                'Request Review',
                ucfirst((string) $request->input('decision', $request->input('status', 'reviewed'))).' a student requirement.',
            ],
            CoordinatorRequirementController::class.'@store',
            CoordinatorRequirementController::class.'@update',
            CoordinatorRequirementController::class.'@reorder',
            CoordinatorRequirementController::class.'@destroy' => ['Requirement Management', 'Updated the internship requirement checklist.'],
            CoordinatorTravelController::class.'@startTravel' => ['Travel Monitoring', 'Started a coordinator travel session.'],
            CoordinatorTravelController::class.'@departure' => ['Travel Monitoring', 'Captured a travel departure point.'],
            CoordinatorTravelController::class.'@arrival' => ['Travel Monitoring', 'Recorded travel arrival.'],
            CoordinatorTravelController::class.'@uploadArrivalPhoto' => ['Travel Monitoring', 'Uploaded travel arrival evidence.'],
            CoordinatorTravelController::class.'@endTravel' => ['Travel Monitoring', 'Completed a coordinator travel session.'],
            CoordinatorTravelController::class.'@cancel' => ['Travel Management', 'Cancelled a student travel session.'],

            ProgramHeadDocumentController::class.'@downloadRequirement',
            ProgramHeadDocumentController::class.'@downloadMoa' => ['Document Access', 'Downloaded a protected document.'],
            ProgramHeadDocumentController::class.'@reviewRequirement' => [
                'Document Decision',
                ucfirst((string) $request->input('decision')).' a document.',
            ],
            ProgramHeadDocumentController::class.'@reviewMoa' => [
                'Document Decision',
                ucfirst((string) $request->input('decision')).' an MOA document.',
            ],
            ProgramHeadTravelController::class.'@verifyCheckpoint' => ['Travel Review', 'Reviewed a student travel checkpoint.'],

            VPAAApprovalController::class.'@review',
            VPAAMOAController::class.'@review' => [
                'Request Decision',
                ucfirst((string) $request->input('decision')).' a request.',
            ],
            AnnouncementController::class.'@store' => ['Announcement', 'Created an announcement.'],
            AnnouncementController::class.'@update' => ['Announcement', 'Updated an announcement.'],
            AnnouncementController::class.'@archive' => ['Announcement', 'Archived an announcement.'],
            AnnouncementController::class.'@restore' => ['Announcement', 'Restored an announcement.'],
            AnnouncementController::class.'@destroy' => ['Announcement', 'Deleted an announcement.'],

            SupervisorManagementController::class.'@reviewAttendance' => ['Attendance Review', 'Reviewed student attendance.'],
            SupervisorManagementController::class.'@saveEvaluation' => $request->input('status') === 'submitted'
                ? ['Evaluation', 'Submitted a student evaluation.']
                : ['Evaluation', 'Updated an evaluation draft.'],

            AdminUserController::class.'@store' => ['User Management', 'Created a user account.'],
            AdminUserController::class.'@update' => ['User Management', 'Updated a user account.'],
            AdminUserController::class.'@destroy' => ['User Management', 'Deactivated a user account.'],
            AdminUserController::class.'@updateRole' => ['Roles and Permissions', 'Updated roles or permissions.'],
            AdminUserController::class.'@toggleStatus' => $this->userStatusActivity($request),
            AdminUserController::class.'@resetPassword' => ['User Management', 'Reset a user account password.'],
            AdminSystemController::class.'@updateSettings' => ['System Settings', 'Updated system settings.'],
            AdminSystemController::class.'@storeCollege',
            AdminSystemController::class.'@updateCollege',
            AdminSystemController::class.'@destroyCollege',
            AdminSystemController::class.'@storeProgram',
            AdminSystemController::class.'@updateProgram',
            AdminSystemController::class.'@destroyProgram',
            AdminSystemController::class.'@storeSection',
            AdminSystemController::class.'@updateSection',
            AdminSystemController::class.'@destroySection' => ['Academic Settings', 'Updated academic settings.'],
            AdminSystemController::class.'@reports' => ['Report', 'Generated a report.'],

            default => null,
        };
    }

    private function smartClockActivity(Request $request, Response $response): array
    {
        if (in_array($request->input('status'), ['absent', 'holiday'], true)) {
            return ['Attendance', 'Submitted attendance status.'];
        }

        $content = strtolower((string) $response->getContent());

        return str_contains($content, 'time out')
            ? ['Time Out', 'Recorded time out.']
            : ['Time In', 'Recorded time in.'];
    }

    private function userStatusActivity(Request $request): array
    {
        $user = $request->route('user');

        return $user?->is_active
            ? ['User Management', 'Activated a user account.']
            : ['User Management', 'Deactivated a user account.'];
    }
}
