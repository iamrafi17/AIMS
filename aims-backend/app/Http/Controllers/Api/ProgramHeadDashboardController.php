<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Attendance;
use Illuminate\Http\Request;

class ProgramHeadDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get students in the user's college (assuming program heads are assigned to colleges)
        $collegeId = $user->student?->college_id ?? null;
        
        $students = Student::when($collegeId, function ($query) use ($collegeId) {
            $query->where('college_id', $collegeId);
        });

        $statistics = [
            'total_students' => $students->count(),
            'active_interns' => (clone $students)->where('internship_status', 'active')->count(),
            'pending_reviews' => (clone $students)->where('registration_status', 'pending')->count(),
        ];

        // Get attendance summary
        $attendanceSummary = Attendance::when($collegeId, function ($query) use ($collegeId) {
                $query->whereHas('student', function ($q) use ($collegeId) {
                    $q->where('college_id', $collegeId);
                });
            })
            ->whereMonth('date', now()->month)
            ->selectRaw('
                COUNT(CASE WHEN status = "present" THEN 1 END) as present,
                COUNT(CASE WHEN status = "absent" THEN 1 END) as absent
            ')
            ->first();

        return response()->json([
            'statistics' => $statistics,
            'attendance_summary' => [
                'present' => $attendanceSummary->present ?? 0,
                'absent' => $attendanceSummary->absent ?? 0,
            ],
        ]);
    }
}
