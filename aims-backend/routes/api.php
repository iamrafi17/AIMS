<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollegeController;
use App\Http\Controllers\Api\CoordinatorDashboardController;
use App\Http\Controllers\Api\CoordinatorStudentController;
use App\Http\Controllers\Api\ProgramHeadDashboardController;
use App\Http\Controllers\Api\PublicPortalController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StudentDashboardController;
use App\Http\Controllers\Api\StudentRequirementController;
use App\Http\Controllers\Api\SupervisorDashboardController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('/portal', [PublicPortalController::class, 'index']);

// Public data routes
Route::get('/colleges', [CollegeController::class, 'index']);
Route::get('/colleges/{college}/programs', [CollegeController::class, 'programs']);
Route::get('/colleges/{college}/htes', [CollegeController::class, 'htes']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'removeAvatar']);

    // Announcements (all authenticated users)
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);

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
    });

    // Coordinator Routes
    Route::middleware('role:coordinator')->prefix('coordinator')->group(function () {
        Route::get('/dashboard', [CoordinatorDashboardController::class, 'index']);
        Route::get('/students/options', [CoordinatorStudentController::class, 'options']);
        Route::post('/students/import', [CoordinatorStudentController::class, 'importCsv']);
        Route::apiResource('students', CoordinatorStudentController::class);
        Route::post('/students/{student}/approve', [CoordinatorStudentController::class, 'approveRegistration']);
        Route::post('/students/{student}/reject', [CoordinatorStudentController::class, 'rejectRegistration']);
        Route::post('/students/{student}/requirements/{requirement}/review', [CoordinatorStudentController::class, 'reviewRequirement']);
        Route::get('/students/{student}/requirements/{requirement}/download', [CoordinatorStudentController::class, 'downloadRequirement']);
    });

    // Program Head Routes
    Route::middleware('role:program_head')->prefix('program-head')->group(function () {
        Route::get('/dashboard', [ProgramHeadDashboardController::class, 'index']);
    });

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::apiResource('users', AdminUserController::class);
        Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole']);
        Route::put('/users/{user}/status', [AdminUserController::class, 'toggleStatus']);
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
    });

    // Supervisor Routes
    Route::middleware('role:supervisor')->prefix('supervisor')->group(function () {
        Route::get('/dashboard', [SupervisorDashboardController::class, 'index']);
    });
});
