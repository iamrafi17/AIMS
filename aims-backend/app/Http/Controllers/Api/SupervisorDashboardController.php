<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Attendance;
use Illuminate\Http\Request;

class SupervisorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $supervisorId = $request->user()->id;

        // Get students assigned to this supervisor (via evaluations)
        $assignedStudents = Student::whereHas('evaluations', function ($query) use ($supervisorId) {
            $query->where('supervisor_id', $supervisorId);
        })->get();

        $statistics = [
            'total_assigned' => $assignedStudents->count(),
            'pending_evaluations' => $assignedStudents->filter(function ($student) use ($supervisorId) {
                return !$student->evaluations()->where('supervisor_id', $supervisorId)->exists();
            })->count(),
        ];

        // Get attendance summary for assigned students
        $studentIds = $assignedStudents->pluck('id');
        $attendanceSummary = Attendance::whereIn('student_id', $studentIds)
            ->whereMonth('date', now()->month)
            ->selectRaw('
                COUNT(CASE WHEN status = "present" THEN 1 END) as present,
                COUNT(CASE WHEN status = "absent" THEN 1 END) as absent
            ')
            ->first();

        return response()->json([
            'statistics' => $statistics,
            'assigned_students' => $assignedStudents->take(10),
            'attendance_summary' => [
                'present' => $attendanceSummary->present ?? 0,
                'absent' => $attendanceSummary->absent ?? 0,
            ],
        ]);
    }
}
