<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\InternshipTask;
use App\Models\Student;
use App\Models\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupervisorManagementController extends Controller
{
    public function progress(Request $request)
    {
        $students = $this->students($request)->get();

        return response()->json([
            'students' => $students->map(fn (Student $student) => $this->studentPayload($student)),
            'tasks' => InternshipTask::with('student:id,student_id,first_name,last_name')
                ->where('supervisor_id', $request->user()->id)
                ->latest()->get(),
            'overview' => [
                'assigned' => $students->count(),
                'active' => $students->where('internship_status', 'active')->count(),
                'completed_tasks' => $students->sum(fn (Student $student) => $student->tasks->where('status', 'completed')->count()),
                'open_tasks' => $students->sum(fn (Student $student) => $student->tasks->whereIn('status', ['assigned', 'in_progress'])->count()),
            ],
        ]);
    }

    public function storeTask(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
        ]);
        $student = $this->assignedStudent($request, (int) $data['student_id']);
        $task = InternshipTask::create([...$data, 'student_id' => $student->id, 'supervisor_id' => $request->user()->id]);
        SystemNotification::sendToUser(
            $student->user_id,
            'New internship task assigned',
            $task->title.($task->due_date ? ' · Due '.$task->due_date->format('M j, Y') : ''),
            'task',
            '/student/dashboard',
        );

        return response()->json(['message' => 'Task assigned successfully.', 'task' => $task], 201);
    }

    public function updateTask(Request $request, InternshipTask $task)
    {
        abort_unless($task->supervisor_id === $request->user()->id, 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['assigned', 'in_progress', 'completed', 'cancelled'])],
        ]);
        $task->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        return response()->json(['message' => 'Task status updated.', 'task' => $task]);
    }

    public function attendance(Request $request)
    {
        $studentIds = $this->students($request)->pluck('id');
        $query = Attendance::with(['student.user', 'student.program', 'student.hte'])
            ->whereIn('student_id', $studentIds)->latest('date');

        if ($request->filled('student_id')) $query->where('student_id', $request->input('student_id'));
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('review_status')) $query->where('supervisor_review_status', $request->input('review_status'));
        if ($request->filled('date_from')) $query->whereDate('date', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('date', '<=', $request->input('date_to'));

        return response()->json([
            'records' => $query->paginate(20),
            'students' => $this->students($request)->get()->map(fn (Student $student) => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => trim($student->first_name.' '.$student->last_name),
            ]),
        ]);
    }

    public function reviewAttendance(Request $request, Attendance $attendance)
    {
        $this->assignedStudent($request, $attendance->student_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'flagged'])],
            'feedback' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('status') === 'flagged')],
        ]);
        $attendance->update([
            'supervisor_review_status' => $data['status'],
            'supervisor_feedback' => $data['feedback'] ?? null,
            'supervisor_reviewed_by' => $request->user()->id,
            'supervisor_reviewed_at' => now(),
        ]);

        return response()->json(['message' => $data['status'] === 'approved' ? 'Attendance reviewed and approved.' : 'Attendance flagged for coordinator review.']);
    }

    public function evaluations(Request $request)
    {
        return response()->json([
            'students' => $this->students($request)->get()->map(fn (Student $student) => $this->studentPayload($student)),
            'evaluations' => Evaluation::with(['student.program', 'student.hte'])
                ->where('supervisor_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function saveEvaluation(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'evaluation_type' => ['required', Rule::in(['midterm', 'final'])],
            'work_quality' => ['required', 'integer', 'between:1,5'],
            'communication' => ['required', 'integer', 'between:1,5'],
            'professionalism' => ['required', 'integer', 'between:1,5'],
            'attendance' => ['required', 'integer', 'between:1,5'],
            'technical_skills' => ['required', 'integer', 'between:1,5'],
            'teamwork' => ['required', 'integer', 'between:1,5'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'submitted'])],
        ]);
        $student = $this->assignedStudent($request, (int) $data['student_id']);
        $evaluation = Evaluation::updateOrCreate(
            ['student_id' => $student->id, 'supervisor_id' => $request->user()->id, 'evaluation_type' => $data['evaluation_type']],
            [...$data, 'submitted_at' => $data['status'] === 'submitted' ? now() : null],
        );

        return response()->json(['message' => $data['status'] === 'submitted' ? 'Evaluation submitted successfully.' : 'Evaluation draft saved.', 'evaluation' => $evaluation]);
    }

    private function students(Request $request)
    {
        return Student::with(['user', 'program', 'hte', 'attendance', 'tasks' => fn ($query) => $query->where('supervisor_id', $request->user()->id), 'evaluations' => fn ($query) => $query->where('supervisor_id', $request->user()->id)])
            ->where('supervisor_id', $request->user()->id)
            ->orderBy('last_name');
    }

    private function assignedStudent(Request $request, int $studentId): Student
    {
        return Student::where('id', $studentId)->where('supervisor_id', $request->user()->id)->firstOrFail();
    }

    private function studentPayload(Student $student): array
    {
        $hours = $student->attendance->sum(function (Attendance $record) {
            $regular = collect([[$record->am_time_in, $record->am_time_out], [$record->pm_time_in, $record->pm_time_out]])
                ->sum(fn ($slot) => $slot[0] && $slot[1] ? $slot[0]->diffInSeconds($slot[1]) / 3600 : 0);
            return $regular + (float) $record->overtime_hours;
        });
        $required = max((float) $student->required_ojt_hours, 1);

        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->join(' ')),
            'avatar_url' => $student->user?->avatar_url,
            'program' => $student->program?->code,
            'hte' => $student->hte?->name,
            'internship_status' => $student->internship_status,
            'rendered_hours' => round($hours, 1),
            'required_hours' => round($required, 1),
            'progress' => round(min(($hours / $required) * 100, 100), 1),
            'attendance_days' => $student->attendance->whereIn('status', ['present', 'late'])->count(),
            'tasks' => $student->tasks,
            'evaluations' => $student->evaluations,
        ];
    }
}
