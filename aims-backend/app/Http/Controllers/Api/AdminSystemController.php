<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
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
            'colleges' => College::withCount('programs')->orderBy('name')->get()
                ->each(fn (College $college) => $college->setAttribute('students_count', Student::forCollege($college->id)->count())),
            'programs' => Program::with('college:id,name,code')
                ->withCount(['enrollments as students_count' => fn ($query) => $query->whereNotNull('student_record_id')])
                ->orderBy('name')->get(),
            'sections' => AcademicSection::with(['program:id,college_id,name,code', 'academicPeriod:id,academic_year,semester,status'])->newestPeriodFirst()->orderBy('name')->get(),
            'academic_periods' => AcademicPeriod::orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('start_date')->get(['id', 'academic_year', 'semester', 'status']),
            'overview' => [
                'colleges' => College::count(),
                'programs' => Program::count(),
                'sections' => AcademicSection::count(),
                'enrolled_students' => Student::count(),
            ],
        ]);
    }

    public function storeCollege(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:colleges,code'],
            'required_ojt_hours' => ['sometimes', 'numeric', 'between:1,2000'],
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
            'required_ojt_hours' => ['sometimes', 'numeric', 'between:1,2000'],
        ]);
        $college->update($data);

        return response()->json(['message' => 'College updated successfully.', 'college' => $college]);
    }

    public function destroyCollege(College $college)
    {
        $this->ensureNoConnectedRecords($college->name, 'college_id', $college->id, [
            'programs', 'students', 'users', 'ojt_enrollments', 'htes', 'holidays', 'moas',
        ]);

        $this->deleteAcademicRecord($college, 'college');

        return response()->json(['message' => 'College deleted successfully.']);
    }

    public function storeProgram(Request $request)
    {
        $data = $request->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:programs,code'],
        ]);

        return response()->json(['message' => 'Program created successfully.', 'program' => Program::create($data)], 201);
    }

    public function updateProgram(Request $request, Program $program)
    {
        $data = $request->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('programs', 'code')->ignore($program)],
        ]);
        $program->update($data);

        return response()->json(['message' => 'Program updated successfully.', 'program' => $program]);
    }

    public function destroyProgram(Program $program)
    {
        $this->ensureNoConnectedRecords($program->name, 'program_id', $program->id, [
            'students', 'users', 'ojt_enrollments', 'htes', 'holidays', 'moas',
            'academic_sections', 'program_requirements', 'report_templates', 'generated_reports',
        ]);

        $this->deleteAcademicRecord($program, 'program');

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
        $this->ensureNoConnectedRecords($section->name, 'academic_section_id', $section->id, [
            'students', 'ojt_enrollments', 'hte_batch_schedules',
        ]);

        $this->deleteAcademicRecord($section, 'section');

        return response()->json(['message' => 'Section deleted successfully.']);
    }

    public function settings()
    {
        $savedSettings = SystemSetting::orderBy('group')->orderBy('id')->get()->keyBy('key');
        $defaults = collect($this->defaultSettings());

        return response()->json([
            'settings' => $defaults->map(fn (array $setting) => $savedSettings->get($setting['key']) ?? new SystemSetting($setting))
                ->groupBy('group'),
            'has_missing_settings' => $defaults->pluck('key')->diff($savedSettings->keys())->isNotEmpty(),
            'updated_at' => SystemSetting::max('updated_at'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $defaults = collect($this->defaultSettings())->keyBy('key');
        $rules = ['settings' => ['required', 'array:'.$defaults->keys()->implode(',')]];
        foreach ($defaults as $key => $setting) {
            $rules['settings.'.$key] = match ($setting['type']) {
                'boolean' => ['sometimes', 'required', 'boolean'],
                'integer' => ['sometimes', 'required', 'integer', 'min:0'],
                default => ['sometimes', 'required', 'string'],
            };
        }
        $rules['settings.minimum_password_length'] = ['sometimes', 'required', 'integer', 'between:8,72'];
        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $defaults) {
            foreach ($data['settings'] as $key => $value) {
                $setting = $defaults->get($key);
                SystemSetting::updateOrCreate(['key' => $key], [
                    ...$setting,
                    'value' => $this->normalizeSetting($setting['type'], $value),
                ]);
            }
        });

        return response()->json(['message' => 'System settings saved successfully.']);
    }

    public function reports()
    {
        $attendanceByStatus = Attendance::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $studentsByProgram = Program::withCount([
            'enrollments as students_count' => fn ($query) => $query->whereNotNull('student_record_id'),
        ])
            ->orderByDesc('students_count')->limit(8)->get(['id', 'name', 'code']);
        $requirements = InternshipRequirement::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'overview' => [
                'users' => User::count(),
                'students' => Student::count(),
                'active_interns' => Student::forInternshipStatus('active')->count(),
                'partner_htes' => HTE::where('is_active', true)->count(),
                'approved_moas' => MOA::where('status', 'approved')->count(),
                'attendance_records' => Attendance::count(),
            ],
            'users_by_role' => User::query()
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->selectRaw('roles.code AS role, COUNT(*) AS total')
                ->groupBy('roles.code')
                ->pluck('total', 'role'),
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
        $periodData = $request->validate([
            'academic_period_id' => ['required', 'integer', Rule::exists('academic_periods', 'id')],
        ]);
        $period = AcademicPeriod::findOrFail($periodData['academic_period_id']);
        if ($section && (int) $section->academic_period_id !== $period->id) {
            abort_if(Student::forAcademicSection($section->id)->exists()
                || DB::table('ojt_enrollments')->where('academic_section_id', $section->id)->exists()
                || DB::table('hte_batch_schedules')->where('academic_section_id', $section->id)->exists(),
                422, 'This section has linked records and cannot be moved to another academic period.');
        }

        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('academic_sections')->where(fn ($query) => $query
                    ->where('program_id', $request->input('program_id'))
                    ->where('academic_period_id', $period->id))
                    ->ignore($section),
            ],
            'year_level' => ['required', 'integer', 'between:1,6'],
        ]);

        return [...$data, 'academic_period_id' => $period->id];
    }

    private function ensureNoConnectedRecords(string $name, string $foreignKey, int $id, array $tables): void
    {
        foreach ($tables as $table) {
            if (DB::table($table)->where($foreignKey, $id)->exists()) {
                abort(422, "{$name} has connected records and cannot be deleted.");
            }
        }
    }

    private function deleteAcademicRecord(College|Program|AcademicSection $record, string $type): void
    {
        try {
            $record->delete();
        } catch (QueryException) {
            abort(422, "This {$type} is referenced by another record and cannot be deleted.");
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

    private function defaultSettings(): array
    {
        return array_map(fn (array $setting) => [
            'group' => $setting[0],
            'key' => $setting[1],
            'value' => $setting[2],
            'type' => $setting[3],
            'label' => $setting[4],
            'description' => $setting[5],
        ], [
            ['general', 'system_name', 'Academic Internship Monitoring System', 'string', 'System name', 'Name displayed throughout the portal.'],
            ['general', 'campus_name', 'Marinduque State University – Santa Cruz Campus', 'string', 'Campus name', 'Official campus identity.'],
            ['internship', 'default_ojt_hours', '486', 'integer', 'Default required OJT hours', 'Used when an enrollment has no program-specific requirement.'],
            ['internship', 'allow_past_attendance', '0', 'boolean', 'Allow past attendance by default', 'Coordinators can still override this for individual students.'],
            ['security', 'session_timeout_minutes', '120', 'integer', 'Session timeout (minutes)', 'Recommended inactivity limit for authenticated sessions.'],
            ['security', 'minimum_password_length', '8', 'integer', 'Minimum password length', 'Minimum accepted account password length.'],
            ['notifications', 'email_notifications', '1', 'boolean', 'Email notifications', 'Send important approval and deadline notices by email.'],
            ['notifications', 'moa_expiration_days', '60', 'integer', 'MOA expiration warning (days)', 'Number of days before expiration alerts begin.'],
        ]);
    }
}
