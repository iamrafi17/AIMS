<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminSystemController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollegeController;
use App\Http\Controllers\Api\CoordinatorAttendanceController;
use App\Http\Controllers\Api\CoordinatorDashboardController;
use App\Http\Controllers\Api\CoordinatorHTEController;
use App\Http\Controllers\Api\CoordinatorRequirementController;
use App\Http\Controllers\Api\CoordinatorStudentController;
use App\Http\Controllers\Api\CoordinatorTravelController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgramHeadDashboardController;
use App\Http\Controllers\Api\ProgramHeadDocumentController;
use App\Http\Controllers\Api\ProgramHeadStudentController;
use App\Http\Controllers\Api\ProgramHeadTravelController;
use App\Http\Controllers\Api\PublicPortalController;
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
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/registration/enrollment/{schoolId}', [AuthController::class, 'enrollment']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/portal', [PublicPortalController::class, 'index']);

// Public data routes
Route::get('/colleges', [CollegeController::class, 'index']);
Route::get('/colleges/{college}/programs', [CollegeController::class, 'programs']);
Route::get('/colleges/{college}/htes', [CollegeController::class, 'htes']);

// Protected Routes
Route::middleware(['auth:sanctum', 'audit'])->group(function () {
    // User routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/account', [AuthController::class, 'updateAccountInformation']);
    Route::put('/profile/information', [AuthController::class, 'updateProfileInformation']);
    Route::put('/profile/contact', [AuthController::class, 'updateContactInformation']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'removeAvatar']);

    // Announcements (all authenticated users)
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
    Route::post('/announcements/{announcement}/read', [AnnouncementController::class, 'markRead']);
    Route::get('/announcements/{announcement}/download', [AnnouncementController::class, 'download']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'read']);

    // Student Routes
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index']);
        Route::get('/attendance', [StudentAttendanceController::class, 'index']);
        Route::get('/attendance/workspace', [StudentAttendanceController::class, 'workspace']);
        Route::post('/attendance/entry', [StudentAttendanceController::class, 'saveEntry']);
        Route::get('/attendance/quick-clock', [StudentAttendanceController::class, 'quickClock']);
        Route::post('/attendance/quick-clock', [StudentAttendanceController::class, 'smartLog']);
        Route::post('/attendance/clock-in', [StudentAttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [StudentAttendanceController::class, 'clockOut']);
        Route::get('/requirements', [StudentRequirementController::class, 'index']);
        Route::post('/requirements/{id}/upload', [StudentRequirementController::class, 'upload']);
        Route::get('/requirements/{id}/download', [StudentRequirementController::class, 'download']);
        Route::get('/travel', [StudentTravelController::class, 'index']);
        Route::post('/travel/{travel}/start', [StudentTravelController::class, 'start']);
        Route::post('/travel/checkpoints/{checkpoint}', [StudentTravelController::class, 'checkpoint']);
        Route::post('/travel/{travel}/end', [StudentTravelController::class, 'end']);
    });

    // Coordinator Routes
    Route::middleware('role:coordinator')->prefix('coordinator')->group(function () {
        Route::get('/dashboard', [CoordinatorDashboardController::class, 'index']);
        Route::get('/attendance', [CoordinatorAttendanceController::class, 'index']);
        Route::get('/attendance/{attendance}', [CoordinatorAttendanceController::class, 'show']);
        Route::put('/attendance/{attendance}', [CoordinatorAttendanceController::class, 'update']);
        Route::delete('/attendance/{attendance}', [CoordinatorAttendanceController::class, 'destroy']);
        Route::put('/attendance/{attendance}/verify', [CoordinatorAttendanceController::class, 'verify']);
        Route::post('/attendance/{attendance}/journal-review', [CoordinatorAttendanceController::class, 'reviewJournal']);
        Route::get('/htes', [CoordinatorHTEController::class, 'index']);
        Route::post('/htes', [CoordinatorHTEController::class, 'store']);
        Route::put('/htes/deployments/{student}', [CoordinatorHTEController::class, 'deploy']);
        Route::post('/htes/holidays', [CoordinatorHTEController::class, 'storeHoliday']);
        Route::put('/htes/holidays/{holiday}', [CoordinatorHTEController::class, 'updateHoliday']);
        Route::delete('/htes/holidays/{holiday}', [CoordinatorHTEController::class, 'destroyHoliday']);
        Route::post('/htes/moas', [CoordinatorHTEController::class, 'storeMoa']);
        Route::put('/htes/moas/{moa}', [CoordinatorHTEController::class, 'updateMoa']);
        Route::get('/htes/moas/{moa}/download', [CoordinatorHTEController::class, 'downloadMoa']);
        Route::delete('/htes/moas/{moa}', [CoordinatorHTEController::class, 'destroyMoa']);
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
        Route::apiResource('students', CoordinatorStudentController::class);
        Route::post('/students/{student}/approve', [CoordinatorStudentController::class, 'approveRegistration']);
        Route::post('/students/{student}/reject', [CoordinatorStudentController::class, 'rejectRegistration']);
        Route::put('/students/{student}/deactivate', [CoordinatorStudentController::class, 'deactivate']);
        Route::post('/students/{student}/requirements/{requirement}/review', [CoordinatorStudentController::class, 'reviewRequirement']);
        Route::get('/students/{student}/requirements/{requirement}/download', [CoordinatorStudentController::class, 'downloadRequirement']);
        Route::get('/travel', [CoordinatorTravelController::class, 'index']);
        Route::post('/travel', [CoordinatorTravelController::class, 'store']);
        Route::put('/travel/{travel}', [CoordinatorTravelController::class, 'update']);
        Route::put('/travel/{travel}/cancel', [CoordinatorTravelController::class, 'cancel']);
        Route::delete('/travel/{travel}', [CoordinatorTravelController::class, 'destroy']);
    });

    // Program Head Routes
    Route::middleware('role:program_head')->prefix('program-head')->group(function () {
        Route::get('/dashboard', [ProgramHeadDashboardController::class, 'index']);
        Route::get('/documents', [ProgramHeadDocumentController::class, 'index']);
        Route::post('/documents/requirements/{requirement}/review', [ProgramHeadDocumentController::class, 'reviewRequirement']);
        Route::get('/documents/requirements/{requirement}/download', [ProgramHeadDocumentController::class, 'downloadRequirement']);
        Route::post('/documents/moas/{moa}/review', [ProgramHeadDocumentController::class, 'reviewMoa']);
        Route::get('/documents/moas/{moa}/download', [ProgramHeadDocumentController::class, 'downloadMoa']);
        Route::get('/travel', [ProgramHeadTravelController::class, 'index']);
        Route::put('/travel/checkpoints/{checkpoint}/verify', [ProgramHeadTravelController::class, 'verifyCheckpoint']);
        Route::get('/travel/checkpoints/{checkpoint}/photo', [ProgramHeadTravelController::class, 'photo']);
        Route::get('/students', [ProgramHeadStudentController::class, 'index']);
    });

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::apiResource('users', AdminUserController::class);
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
        Route::get('/audit', [AdminSystemController::class, 'audit']);
        Route::get('/reports', [AdminSystemController::class, 'reports']);
    });

    // Supervisor Routes
    Route::middleware('role:supervisor')->prefix('supervisor')->group(function () {
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
    Route::middleware('role:vpaa')->prefix('vpaa')->group(function () {
        Route::get('/dashboard', [VPAADashboardController::class, 'index']);
        Route::get('/approvals', [VPAAApprovalController::class, 'index']);
        Route::put('/approvals/{approval}', [VPAAApprovalController::class, 'review']);
        Route::get('/approvals/{approval}/download', [VPAAApprovalController::class, 'download']);
        Route::get('/moas', [VPAAMOAController::class, 'index']);
        Route::put('/moas/{approval}', [VPAAMOAController::class, 'review']);
        Route::get('/moas/{approval}/download', [VPAAMOAController::class, 'download']);
        Route::get('/travel', [ProgramHeadTravelController::class, 'index']);
        Route::put('/travel/checkpoints/{checkpoint}/verify', [ProgramHeadTravelController::class, 'verifyCheckpoint']);
        Route::get('/travel/checkpoints/{checkpoint}/photo', [ProgramHeadTravelController::class, 'photo']);
        Route::get('/announcements', [AnnouncementController::class, 'manage']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::post('/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::put('/announcements/{announcement}/archive', [AnnouncementController::class, 'archive']);
        Route::put('/announcements/{announcement}/restore', [AnnouncementController::class, 'restore']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
    });
});
