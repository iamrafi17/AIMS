<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSystemController extends Controller
{
    public function academic()
    {
        return response()->json([
            'colleges' => College::withCount(['programs', 'students'])->orderBy('name')->get(),
            'programs' => Program::with('college:id,name,code')->withCount('students')->orderBy('name')->get(),
            'sections' => AcademicSection::with('program:id,college_id,name,code')->orderByDesc('academic_year')->orderBy('name')->get(),
            'overview' => [
                'colleges' => College::where('is_active', true)->count(),
                'programs' => Program::where('is_active', true)->count(),
                'sections' => AcademicSection::where('is_active', true)->count(),
                'enrolled_students' => Student::count(),
            ],
        ]);
    }

    public function storeCollege(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:colleges,code'],
            'required_ojt_hours' => ['required', 'numeric', 'between:1,2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'message' => 'College created successfully.',
            'college' => College::create($data),
        ], 201);
    }

    public function updateCollege(Request $request, College $college)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', Rule::unique('colleges', 'code')->ignore($college)],
            'required_ojt_hours' => ['required', 'numeric', 'between:1,2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $college->update($data);

        return response()->json(['message' => 'College updated successfully.', 'college' => $college]);
    }

    public function destroyCollege(College $college)
    {
        abort_if($college->programs()->exists() || $college->students()->exists(), 422, 'Deactivate this college instead; it still has connected records.');
        $college->delete();

        return response()->json(['message' => 'College deleted successfully.']);
    }

    public function storeProgram(Request $request)
    {
        $data = $request->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:programs,code'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json(['message' => 'Program created successfully.', 'program' => Program::create($data)], 201);
    }

    public function updateProgram(Request $request, Program $program)
    {
        $data = $request->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('programs', 'code')->ignore($program)],
            'is_active' => ['required', 'boolean'],
        ]);
        $program->update($data);

        return response()->json(['message' => 'Program updated successfully.', 'program' => $program]);
    }

    public function destroyProgram(Program $program)
    {
        abort_if($program->students()->exists(), 422, 'Deactivate this program instead; students are connected to it.');
        $program->delete();

        return response()->json(['message' => 'Program deleted successfully.']);
    }

    public function storeSection(Request $request)
    {
        $data = $this->sectionData($request);

        return response()->json(['message' => 'Section created successfully.', 'section' => AcademicSection::create($data)], 201);
    }

    public function updateSection(Request $request, AcademicSection $section)
    {
        $section->update($this->sectionData($request, $section));

        return response()->json(['message' => 'Section updated successfully.', 'section' => $section]);
    }

    public function destroySection(AcademicSection $section)
    {
        $section->delete();

        return response()->json(['message' => 'Section deleted successfully.']);
    }

    public function settings()
    {
        return response()->json([
            'settings' => SystemSetting::orderBy('group')->orderBy('id')->get()->groupBy('group'),
            'updated_at' => SystemSetting::max('updated_at'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        foreach ($data['settings'] as $key => $value) {
            $setting = SystemSetting::where('key', $key)->firstOrFail();
            $setting->update(['value' => $this->normalizeSetting($setting->type, $value)]);
        }

        return response()->json(['message' => 'System settings saved successfully.']);
    }

    public function audit(Request $request)
    {
        $query = AuditLog::with('user:id,name,email,role')->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('action', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('role', $request->input('role')));
        }

        return response()->json($query->paginate(25));
    }

    public function reports()
    {
        $attendanceByStatus = Attendance::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $studentsByProgram = Program::withCount('students')
            ->orderByDesc('students_count')->limit(8)->get(['id', 'name', 'code']);
        $requirements = InternshipRequirement::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'overview' => [
                'users' => User::count(),
                'students' => Student::count(),
                'active_interns' => Student::where('internship_status', 'active')->count(),
                'partner_htes' => HTE::where('is_active', true)->count(),
                'approved_moas' => MOA::where('status', 'approved')->count(),
                'attendance_records' => Attendance::count(),
            ],
            'users_by_role' => User::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role'),
            'students_by_program' => $studentsByProgram,
            'attendance_by_status' => $attendanceByStatus,
            'requirements_by_status' => $requirements,
            'monthly_attendance' => collect(range(5, 0))->map(function (int $monthsAgo) {
                $date = now()->subMonths($monthsAgo);

                return [
                    'label' => $date->format('M'),
                    'total' => Attendance::whereYear('date', $date->year)->whereMonth('date', $date->month)->count(),
                ];
            }),
        ]);
    }

    private function sectionData(Request $request, ?AcademicSection $section = null): array
    {
        return $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('academic_sections')->where(fn ($query) => $query
                    ->where('program_id', $request->input('program_id'))
                    ->where('academic_year', $request->input('academic_year')))
                    ->ignore($section),
            ],
            'year_level' => ['required', 'integer', 'between:1,6'],
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function normalizeSetting(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) max((int) $value, 0),
            default => trim((string) $value),
        };
    }
}
