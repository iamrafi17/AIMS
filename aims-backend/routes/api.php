<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AcademicPeriodController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminSystemController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollegeController;
use App\Http\Controllers\Api\CoordinatorAttendanceController;
use App\Http\Controllers\Api\CoordinatorDashboardController;
use App\Http\Controllers\Api\CoordinatorHTEController;
use App\Http\Controllers\Api\CoordinatorReportController;
use App\Http\Controllers\Api\CoordinatorRequirementController;
use App\Http\Controllers\Api\CoordinatorStudentController;
use App\Http\Controllers\Api\CoordinatorTravelController;
use App\Http\Controllers\Api\DailyJournalTemplateController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramHeadDashboardController;
use App\Http\Controllers\Api\ProgramHeadDeploymentController;
use App\Http\Controllers\Api\ProgramHeadDocumentController;
use App\Http\Controllers\Api\ProgramHeadStudentController;
use App\Http\Controllers\Api\ProgramHeadTravelController;
use App\Http\Controllers\Api\PublicPortalController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\ReportTemplateController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StudentDashboardController;
use App\Http\Controllers\Api\StudentRequirementController;
use App\Http\Controllers\Api\StudentTravelController;
use App\Http\Controllers\Api\SupervisorDashboardController;
use App\Http\Controllers\Api\SupervisorManagementController;
use App\Http\Controllers\Api\VPAAApprovalController;
use App\Http\Controllers\Api\VPAADashboardController;
use App\Http\Controllers\Api\VPAAMOAController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
Route::get('/registration/enrollment/{schoolId}', [AuthController::class, 'enrollment'])->middleware('throttle:enrollment');
Route::get('/security-questions', [AuthController::class, 'securityQuestions']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:recovery');
Route::post('/forgot-password/verify', [AuthController::class, 'verifySecurityAnswers'])->middleware('throttle:recovery');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:recovery');
Route::get('/portal', [PublicPortalController::class, 'index']);

// Public data routes
Route::get('/colleges', [CollegeController::class, 'index']);
Route::get('/colleges/{college}/programs', [CollegeController::class, 'programs']);
Route::get('/colleges/{college}/htes', [CollegeController::class, 'htes']);

// Protected Routes
Route::middleware(['auth:sanctum', 'active', 'activity'])->group(function () {
    // User routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/staff/password-setup', [AuthController::class, 'completeStaffPasswordSetup']);
    Route::post('/staff/security-questions', [AuthController::class, 'completeStaffSecurityQuestions']);
    Route::post('/staff/profile-setup', [AuthController::class, 'completeStaffProfile']);
    Route::post('/staff/security-setup', [AuthController::class, 'completeStaffSecuritySetup']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/account', [ProfileController::class, 'updateAccount']);
    Route::put('/profile/information', [ProfileController::class, 'updateStudentInformation']);
    Route::put('/profile/contact', [ProfileController::class, 'updateContact']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);

    // Announcements (all authenticated users)
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
    Route::post('/announcements/{announcement}/read', [AnnouncementController::class, 'markRead']);
    Route::get('/announcements/{announcement}/download', [AnnouncementController::class, 'download']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'read']);

    // Report Template Management (role-aware access and program scoping are enforced by the controller)
    Route::get('/report-templates', [ReportTemplateController::class, 'index']);
    Route::post('/report-templates', [ReportTemplateController::class, 'store']);
    Route::get('/report-templates/active/preview', [ReportTemplateController::class, 'activePreview']);
    Route::get('/report-templates/active/pdf', [ReportTemplateController::class, 'generatePdf']);
    Route::get('/report-templates/{reportTemplate}/preview', [ReportTemplateController::class, 'preview']);
    Route::get('/report-templates/{reportTemplate}/download', [ReportTemplateController::class, 'download']);
    Route::post('/report-templates/{reportTemplate}/validate', [ReportTemplateController::class, 'validateTemplate']);
    Route::post('/report-templates/{reportTemplate}/activate', [ReportTemplateController::class, 'activate']);
    Route::post('/report-templates/{reportTemplate}/deactivate', [ReportTemplateController::class, 'deactivate']);
    Route::delete('/report-templates/{reportTemplate}', [ReportTemplateController::class, 'destroy']);
    Route::get('/generated-reports/{generatedReport}/download', [ReportTemplateController::class, 'downloadGenerated']);

    // Student Routes
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index']);
        Route::get('/attendance', [StudentAttendanceController::class, 'index']);
        Route::get('/attendance/workspace', [StudentAttendanceController::class, 'workspace']);
        Route::post('/attendance/entry', [StudentAttendanceController::class, 'saveEntry']);
        Route::get('/attendance/quick-clock', [StudentAttendanceController::class, 'quickClock']);
        Route::post('/attendance/quick-clock', [StudentAttendanceController::class, 'smartLog']);
        Route::post('/attendance/live-location', [StudentAttendanceController::class, 'shareLiveLocation']);
        Route::post('/attendance/clock-in', [StudentAttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [StudentAttendanceController::class, 'clockOut']);
        Route::get('/requirements', [StudentRequirementController::class, 'index']);
        Route::post('/requirements/{id}/upload', [StudentRequirementController::class, 'upload']);
        Route::get('/requirements/{id}/download', [StudentRequirementController::class, 'download']);
        Route::get('/travel', [StudentTravelController::class, 'index']);
        Route::post('/travel/start', [StudentTravelController::class, 'start']);
        Route::post('/travel/{travel}/departure', [StudentTravelController::class, 'departure']);
        Route::post('/travel/{travel}/checkpoints', [StudentTravelController::class, 'checkpoint']);
        Route::post('/travel/{travel}/arrival', [StudentTravelController::class, 'arrival']);
        Route::post('/travel/{travel}/arrival-photo', [StudentTravelController::class, 'uploadArrivalPhoto']);
        Route::get('/travel/{travel}/arrival-photo', [StudentTravelController::class, 'arrivalPhoto']);
        Route::post('/travel/{travel}/end', [StudentTravelController::class, 'end']);
    });

    // Coordinator Routes
    Route::middleware(['role:coordinator', 'staff.setup'])->prefix('coordinator')->group(function () {
        Route::get('/dashboard', [CoordinatorDashboardController::class, 'index']);
        Route::get('/reports', [CoordinatorReportController::class, 'index']);
        Route::get('/reports/preview', [ReportExportController::class, 'coordinatorPreview']);
        Route::get('/reports/export/{format}', [ReportExportController::class, 'coordinator'])->whereIn('format', ['csv', 'xlsx', 'pdf']);
        Route::get('/attendance', [CoordinatorAttendanceController::class, 'index']);
        Route::get('/attendance/live-locations', [CoordinatorAttendanceController::class, 'liveLocations']);
        Route::get('/attendance/{attendance}', [CoordinatorAttendanceController::class, 'show']);
        Route::get('/attendance/{attendance}/journal-preview', [DailyJournalTemplateController::class, 'preview']);
        Route::post('/attendance/{attendance}/journal-review', [CoordinatorAttendanceController::class, 'reviewJournal']);
        Route::get('/htes', [CoordinatorHTEController::class, 'index']);
        Route::get('/htes/search', [CoordinatorHTEController::class, 'search']);
        Route::post('/htes', [CoordinatorHTEController::class, 'store']);
        Route::put('/htes/deployments/{student}', [CoordinatorHTEController::class, 'deploy']);
        Route::post('/htes/holidays', [CoordinatorHTEController::class, 'storeHoliday']);
        Route::put('/htes/holidays/{holiday}', [CoordinatorHTEController::class, 'updateHoliday']);
        Route::delete('/htes/holidays/{holiday}', [CoordinatorHTEController::class, 'destroyHoliday']);
        Route::post('/htes/moas', [CoordinatorHTEController::class, 'storeMoa']);
        Route::put('/htes/moas/{moa}', [CoordinatorHTEController::class, 'updateMoa']);
        Route::get('/htes/moas/{moa}/download', [CoordinatorHTEController::class, 'downloadMoa']);
        Route::get('/htes/{hte}/batch-schedules', [CoordinatorHTEController::class, 'batchSchedules']);
        Route::put('/htes/{hte}/batch-schedules/{academicSection}', [CoordinatorHTEController::class, 'saveBatchSchedule']);
        Route::post('/htes/{hte}/link', [CoordinatorHTEController::class, 'link']);
        Route::post('/htes/{hte}/departments', [CoordinatorHTEController::class, 'storeDepartment']);
        Route::get('/htes/{hte}', [CoordinatorHTEController::class, 'show']);
        Route::put('/htes/{hte}', [CoordinatorHTEController::class, 'update']);
        Route::delete('/htes/{hte}', [CoordinatorHTEController::class, 'destroy']);
        Route::get('/requirements', [CoordinatorRequirementController::class, 'index']);
        Route::post('/requirements', [CoordinatorRequirementController::class, 'store']);
        Route::put('/requirements/order', [CoordinatorRequirementController::class, 'reorder']);
        Route::put('/requirements/{programRequirement}', [CoordinatorRequirementController::class, 'update']);
        Route::delete('/requirements/{programRequirement}', [CoordinatorRequirementController::class, 'destroy']);
        Route::get('/students/options', [CoordinatorStudentController::class, 'options']);
        Route::get('/students/enrollments', [CoordinatorStudentController::class, 'enrollments']);
        Route::post('/students/import', [CoordinatorStudentController::class, 'importCsv']);
        Route::post('/students/import/confirm', [CoordinatorStudentController::class, 'confirmCsvImport']);
        Route::apiResource('students', CoordinatorStudentController::class);
        Route::post('/students/{student}/requirements/{requirement}/review', [CoordinatorStudentController::class, 'reviewRequirement']);
        Route::get('/students/{student}/requirements/{requirement}/download', [CoordinatorStudentController::class, 'downloadRequirement']);
        Route::get('/travel/{travel}/arrival-photo', [CoordinatorTravelController::class, 'arrivalPhoto']);
        Route::post('/travel/{travel}/reports', [CoordinatorTravelController::class, 'generateReport']);
        Route::put('/travel/reports/{report}', [CoordinatorTravelController::class, 'updateReport']);
        Route::post('/travel/reports/{report}/submit', [CoordinatorTravelController::class, 'submitReport']);
        Route::get('/travel/{travel}', [CoordinatorTravelController::class, 'show']);
        Route::get('/travel', [CoordinatorTravelController::class, 'index']);
    });

    // Program Head Routes
    Route::middleware(['role:program_head', 'staff.setup'])->prefix('program-head')->group(function () {
        Route::get('/dashboard', [ProgramHeadDashboardController::class, 'index']);
        Route::get('/reports', [ProgramHeadDashboardController::class, 'index']);
        Route::get('/reports/preview', [ReportExportController::class, 'programHeadPreview']);
        Route::get('/reports/export/{format}', [ReportExportController::class, 'programHead'])->whereIn('format', ['csv', 'xlsx', 'pdf']);
        Route::put('/deployments/{approval}/review', [ProgramHeadDeploymentController::class, 'review']);
        Route::get('/documents', [ProgramHeadDocumentController::class, 'index']);
        Route::post('/documents/requirements/{requirement}/review', [ProgramHeadDocumentController::class, 'reviewRequirement']);
        Route::get('/documents/requirements/{requirement}/download', [ProgramHeadDocumentController::class, 'downloadRequirement']);
        Route::post('/documents/moas/{moa}/review', [ProgramHeadDocumentController::class, 'reviewMoa']);
        Route::get('/documents/moas/{moa}/download', [ProgramHeadDocumentController::class, 'downloadMoa']);
        Route::get('/travel', [ProgramHeadTravelController::class, 'index']);
        Route::put('/travel/reports/{report}/review', [ProgramHeadTravelController::class, 'reviewReport']);
        Route::get('/travel/{travel}/arrival-photo', [ProgramHeadTravelController::class, 'arrivalPhoto']);
        Route::get('/students', [ProgramHeadStudentController::class, 'index']);
    });

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/supervisor-assignments', [AdminUserController::class, 'supervisorAssignments']);
        Route::apiResource('users', AdminUserController::class);
        Route::delete('/users/{user}/permanent', [AdminUserController::class, 'permanentDestroy']);
        Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole']);
        Route::put('/users/{user}/status', [AdminUserController::class, 'toggleStatus']);
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
        Route::get('/academic', [AdminSystemController::class, 'academic']);
        Route::post('/academic/colleges', [AdminSystemController::class, 'storeCollege']);
        Route::put('/academic/colleges/{college}', [AdminSystemController::class, 'updateCollege']);
        Route::delete('/academic/colleges/{college}', [AdminSystemController::class, 'destroyCollege']);
        Route::post('/academic/programs', [AdminSystemController::class, 'storeProgram']);
        Route::put('/academic/programs/{program}', [AdminSystemController::class, 'updateProgram']);
        Route::delete('/academic/programs/{program}', [AdminSystemController::class, 'destroyProgram']);
        Route::post('/academic/sections', [AdminSystemController::class, 'storeSection']);
        Route::put('/academic/sections/{section}', [AdminSystemController::class, 'updateSection']);
        Route::delete('/academic/sections/{section}', [AdminSystemController::class, 'destroySection']);
        Route::get('/settings', [AdminSystemController::class, 'settings']);
        Route::put('/settings', [AdminSystemController::class, 'updateSettings']);
        Route::get('/academic-periods', [AcademicPeriodController::class, 'index']);
        Route::post('/academic-periods', [AcademicPeriodController::class, 'store']);
        Route::put('/academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'update']);
        Route::put('/academic-periods/{academicPeriod}/activate', [AcademicPeriodController::class, 'activate']);
        Route::put('/academic-periods/{academicPeriod}/complete', [AcademicPeriodController::class, 'complete']);
        Route::delete('/academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'destroy']);
        Route::get('/reports', [AdminSystemController::class, 'reports']);
    });

    // Supervisor Routes
    Route::middleware(['role:supervisor', 'staff.setup'])->prefix('supervisor')->group(function () {
        Route::get('/dashboard', [SupervisorDashboardController::class, 'index']);
        Route::get('/progress', [SupervisorManagementController::class, 'progress']);
        Route::post('/tasks', [SupervisorManagementController::class, 'storeTask']);
        Route::put('/tasks/{task}', [SupervisorManagementController::class, 'updateTask']);
        Route::get('/attendance', [SupervisorManagementController::class, 'attendance']);
        Route::put('/attendance/{attendance}/review', [SupervisorManagementController::class, 'reviewAttendance']);
        Route::get('/evaluations', [SupervisorManagementController::class, 'evaluations']);
        Route::post('/evaluations', [SupervisorManagementController::class, 'saveEvaluation']);
    });

    // VPAA Routes
    Route::middleware(['role:vpaa', 'staff.setup'])->prefix('vpaa')->group(function () {
        Route::get('/dashboard', [VPAADashboardController::class, 'index']);
        Route::get('/reports', [VPAADashboardController::class, 'index']);
        Route::get('/reports/preview', [ReportExportController::class, 'vpaaPreview']);
        Route::get('/reports/export/{format}', [ReportExportController::class, 'vpaa'])->whereIn('format', ['csv', 'xlsx', 'pdf']);
        Route::get('/approvals', [VPAAApprovalController::class, 'index']);
        Route::put('/approvals/{approval}', [VPAAApprovalController::class, 'review']);
        Route::get('/approvals/{approval}/download', [VPAAApprovalController::class, 'download']);
        Route::get('/moas', [VPAAMOAController::class, 'index']);
        Route::put('/moas/{approval}', [VPAAMOAController::class, 'review']);
        Route::get('/moas/{approval}/download', [VPAAMOAController::class, 'download']);
        Route::get('/travel', [ProgramHeadTravelController::class, 'index']);
        Route::put('/travel/reports/{report}/review', [ProgramHeadTravelController::class, 'finalReviewReport']);
        Route::get('/travel/{travel}/arrival-photo', [ProgramHeadTravelController::class, 'arrivalPhoto']);
        Route::get('/announcements', [AnnouncementController::class, 'manage']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::post('/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::put('/announcements/{announcement}/archive', [AnnouncementController::class, 'archive']);
        Route::put('/announcements/{announcement}/restore', [AnnouncementController::class, 'restore']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
    });
});
