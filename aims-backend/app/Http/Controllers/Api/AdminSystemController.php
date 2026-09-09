<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\Attendance;
use App\Models\College;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $college->update(['is_active' => false]);

        return response()->json(['message' => 'College deactivated. Connected records were preserved.']);
    }

    public function permanentDestroyCollege(College $college)
    {
        $this->ensureNoConnectedRecords($college->name, 'college_id', $college->id, [
            'programs', 'students', 'users', 'ojt_enrollments', 'htes', 'holidays', 'moas',
        ]);

        $this->permanentlyDelete($college, 'college');

        return response()->json(['message' => 'College permanently deleted.']);
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
        $program->update(['is_active' => false]);

        return response()->json(['message' => 'Program deactivated. Connected records were preserved.']);
    }

    public function permanentDestroyProgram(Program $program)
    {
        $this->ensureNoConnectedRecords($program->name, 'program_id', $program->id, [
            'students', 'users', 'ojt_enrollments', 'htes', 'holidays', 'moas',
            'academic_sections', 'program_requirements', 'report_templates', 'generated_reports',
        ]);

        $this->permanentlyDelete($program, 'program');

        return response()->json(['message' => 'Program permanently deleted.']);
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
        $section->update(['is_active' => false]);

        return response()->json(['message' => 'Section deactivated. Historical enrollment context was preserved.']);
    }

    public function permanentDestroySection(AcademicSection $section)
    {
        $this->ensureNoConnectedRecords($section->name, 'academic_section_id', $section->id, [
            'students', 'ojt_enrollments', 'hte_batch_schedules',
        ]);

        $this->permanentlyDelete($section, 'section');

        return response()->json(['message' => 'Section permanently deleted.']);
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

    private function ensureNoConnectedRecords(string $name, string $foreignKey, int $id, array $tables): void
    {
        foreach ($tables as $table) {
            if (DB::table($table)->where($foreignKey, $id)->exists()) {
                abort(422, "{$name} has connected records and cannot be permanently deleted. Deactivate it instead.");
            }
        }
    }

    private function permanentlyDelete(College|Program|AcademicSection $record, string $type): void
    {
        try {
            $record->delete();
        } catch (QueryException) {
            abort(422, "This {$type} is referenced by another record and cannot be permanently deleted. Deactivate it instead.");
        }
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
