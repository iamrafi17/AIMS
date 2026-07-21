<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get user statistics by role
        $userStats = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        // Get student statistics
        $studentStats = [
            'total' => Student::count(),
            'pending' => Student::where('registration_status', 'pending')->count(),
            'active' => Student::where('internship_status', 'active')->count(),
            'completed' => Student::where('internship_status', 'completed')->count(),
        ];

        // Get attendance statistics for current month
        $attendanceStats = Attendance::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('
                COUNT(CASE WHEN status = "present" THEN 1 END) as present,
                COUNT(CASE WHEN status = "absent" THEN 1 END) as absent,
                COUNT(CASE WHEN status = "late" THEN 1 END) as late
            ')
            ->first();

        // Get recent activity (last 10 users)
        $recentUsers = User::latest('last_login_at')
            ->whereNotNull('last_login_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'role', 'last_login_at']);

        return response()->json([
            'user_statistics' => $userStats,
            'student_statistics' => $studentStats,
            'attendance_statistics' => [
                'present' => $attendanceStats->present ?? 0,
                'absent' => $attendanceStats->absent ?? 0,
                'late' => $attendanceStats->late ?? 0,
            ],
            'recent_activity' => $recentUsers,
        ]);
    }
}
