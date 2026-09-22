<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\ApprovalRecord;
use App\Models\College;
use App\Models\Holiday;
use App\Models\HTE;
use App\Models\HteBatchSchedule;
use App\Models\HteDepartment;
use App\Models\MOA;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\SystemNotification;
use App\Models\User;
use App\Support\HolidayCalendar;
use App\Support\HteScheduleNormalizer;
use App\Support\ProgramAccess;
use App\Support\StudentRecordNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorHTEController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $htes = HTE::whereHas('programs', fn ($query) => $query
            ->whereKey($programId)
            ->where('hte_programs.is_active', true))
            ->with([
                'moas' => fn ($query) => $query->where('program_id', $programId),
                'moas.college',
                'moas.approver',
                'schedules',
                'batchSchedules' => fn ($query) => $query->whereHas(
                    'academicSection',
                    fn ($sectionQuery) => $sectionQuery->where('program_id', $programId),
                ),
                'batchSchedules.academicSection.program',
                'departments' => fn ($query) => $query->whereHas(
                    'programs',
                    fn ($programs) => $programs
                        ->whereKey($programId)
                        ->where('hte_department_programs.is_active', true),
                ),
                'departments.supervisors.user:id,name,email,user_id,is_active',
            ])
            ->orderBy('name')
            ->get();
        $moas = MOA::with(['hte', 'college', 'program', 'approver'])
            ->where('program_id', $programId)
            ->latest('expiration_date')->get();
        $today = now()->startOfDay();
        $validMoas = $moas->filter(fn (MOA $moa) => $moa->status === 'approved' && $moa->expiration_date->gte($today));
        $deployments = Student::with([
            'user', 'college', 'program', 'hte', 'hteDepartment',
            'supervisor:id,name,email', 'supervisorRecord.user:id,name,email,user_id,is_active',
            'currentInternship.currentPlacement.hteProgram',
            'currentInternship.currentPlacement.departmentProgram.department',
            'currentInternship.currentPlacement.supervisor',
            'currentInternship.scheduleOverrides',
        ])
            ->forProgram($programId)
            ->orderBy('last_name')
            ->get();

        $htes->each(function (HTE $hte) use ($deployments): void {
            $students = $deployments
                ->filter(fn (Student $student) => (int) $student->hte_id === (int) $hte->id)
                ->values();
            $hte->setRelation('students', $students);
            $hte->setAttribute('students_count', $students->count());
        });

        return response()->json([
            'htes' => $htes->map(fn (HTE $hte) => $this->htePayload($hte)),
            'deployments' => $deployments,
            'holidays' => Holiday::with('htes:id,name')
                ->where('program_id', $programId)
                ->orderByDesc('date')
                ->get(),
            'holiday_forecast' => HolidayCalendar::forecastForProgram($programId),
            'moas' => $moas->map(fn (MOA $moa) => $this->moaPayload($moa)),
            'colleges' => College::whereKey($request->user()->college_id)->where('is_active', true)->get(['id', 'name', 'code']),
            'batches' => AcademicSection::with('program:id,code')
                ->where('program_id', $programId)
                ->where('is_active', true)
                ->newestPeriodFirst()
                ->orderBy('year_level')
                ->orderBy('name')
                ->get()
                ->map(fn (AcademicSection $batch) => [
                    'id' => $batch->id,
                    'label' => sprintf(
                        '%s · Year %s-%s · AY %s',
                        $batch->program?->code ?? 'Program',
                        $batch->year_level,
                        $batch->name,
                        $batch->academic_year,
                    ),
                ]),
            'summary' => [
                'total_htes' => $htes->count(),
                'active_htes' => $htes->where('is_active', true)->count(),
                'deployed_students' => $htes->sum('students_count'),
                'geofenced_htes' => $htes->where('geofence_enabled', true)->count(),
                'valid_moas' => $validMoas->count(),
                'expiring_moas' => $validMoas->filter(fn (MOA $moa) => $moa->expiration_date->lte($today->copy()->addDays(60)))->count(),
            ],
            'expiration_alerts' => $moas
                ->filter(fn (MOA $moa) => $moa->status === 'approved' && $moa->expiration_date->lte($today->copy()->addDays(60)))
                ->map(fn (MOA $moa) => $this->moaAlert($moa, $today))
                ->sortBy('days_remaining')
                ->values(),
        ]);
    }

    public function show(Request $request, HTE $hte)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeHte($request->user(), $hte);

        $students = Student::with(['user', 'program', 'college'])
            ->forProgram($programId)
            ->forHte($hte->id)
            ->orderBy('last_name')
            ->get();
        $hte->setRelation('students', $students);
        $hte->setAttribute('students_count', $students->count());

        return response()->json($this->htePayload(
            $hte->load([
                'moas' => fn ($query) => $query->where('program_id', $programId),
                'departments' => fn ($query) => $query->whereHas(
                    'programs',
                    fn ($programs) => $programs
                        ->whereKey($programId)
                        ->where('hte_department_programs.is_active', true),
                ),
                'moas.college', 'moas.approver', 'departments.supervisors.user:id,name,email,user_id,is_active',
                'schedules',
            ])
        ));
    }

    public function search(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $term = trim($validated['search']);

        $htes = HTE::query()
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('address', 'like', '%'.$term.'%')
                    ->orWhere('contact_person', 'like', '%'.$term.'%');
            })
            ->with(['departments.supervisors.user:id,name,email,user_id,is_active', 'schedules'])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function (HTE $hte) use ($programId): array {
                return [
                    ...$this->htePayload($hte),
                    'linked_to_program' => $hte->programs()
                        ->whereKey($programId)
                        ->wherePivot('is_active', true)
                        ->exists(),
                ];
            });

        return response()->json(['htes' => $htes]);
    }

    public function link(Request $request, HTE $hte)
    {
        $programId = ProgramAccess::programId($request->user());
        abort_unless($hte->is_active, 422, 'This HTE is inactive and cannot be linked.');

        $existing = DB::table('hte_programs')
            ->where('hte_id', $hte->id)
            ->where('program_id', $programId)
            ->first();

        DB::transaction(function () use ($request, $hte, $programId, $existing): void {
            DB::table('hte_programs')->updateOrInsert(
                ['hte_id' => $hte->id, 'program_id' => $programId],
                [
                    'linked_by' => $request->user()->id,
                    'is_active' => true,
                    'created_at' => $existing?->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );

            $hte->departments()->pluck('id')->each(
                fn (int $departmentId) => $this->linkDepartmentToProgram(
                    $departmentId,
                    $programId,
                    $request->user()->id,
                )
            );
        });

        return response()->json([
            'message' => $existing?->is_active
                ? 'HTE is already available to this program.'
                : 'Existing HTE, departments, and supervisors linked to your program.',
            'hte' => $this->htePayload($hte->fresh()->load(['departments.supervisors.user:id,name,email,user_id,is_active', 'schedules'])),
        ]);
    }

    public function storeDepartment(Request $request, HTE $hte)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeHte($request->user(), $hte);
        $validated = $request->validate([
            'department_name' => ['required', 'string', 'max:255'],
            'supervisor_full_name' => ['required', 'string', 'max:255'],
        ]);

        [$department, $supervisor] = DB::transaction(fn () => $this->persistDepartment(
            $hte,
            $validated['department_name'],
            $validated['supervisor_full_name'],
            $programId,
            $request->user()->id,
        ));

        return response()->json([
            'message' => $department->wasRecentlyCreated
                ? 'Department and supervisor saved successfully.'
                : ($supervisor->wasRecentlyCreated
                    ? 'Existing department reused and supervisor added.'
                    : 'Existing department and supervisor reused.'),
            'department' => $department->fresh()->load('supervisors.user:id,name,email,user_id,is_active'),
        ], $department->wasRecentlyCreated || $supervisor->wasRecentlyCreated ? 201 : 200);
    }

    public function store(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate([
            ...$this->hteRules($request),
            'departments' => ['nullable', 'array', 'max:20'],
            'departments.*.department_name' => ['required_with:departments', 'string', 'max:255'],
            'departments.*.supervisor_full_name' => ['required_with:departments', 'string', 'max:255'],
        ]);
        $departments = $validated['departments'] ?? [];
        $schedule = $validated['schedule'] ?? null;
        unset($validated['departments'], $validated['schedule']);
        if ($schedule) {
            $this->ensureWorkingDay($schedule['days'], 'schedule.days');
            foreach ($this->legacyHteScheduleKeys() as $key) {
                unset($validated[$key]);
            }
        }
        $hteData = $validated;
        $batchSchedule = null;

        if ($request->has('batch_schedule')) {
            $batchSchedule = $request->validate([
                'batch_schedule' => ['required', 'array'],
                'batch_schedule.academic_section_id' => [
                    'required',
                    Rule::exists('academic_sections', 'id')->where(fn ($query) => $query
                        ->where('program_id', $programId)
                        ->where('is_active', true)),
                ],
                'batch_schedule.grace_period_minutes' => ['sometimes', 'integer', 'between:0,60'],
                ...$this->batchScheduleRules('batch_schedule.days'),
            ])['batch_schedule'];
            $this->ensureWorkingDay($batchSchedule['days'], 'batch_schedule.days');
        }

        $normalizedName = $this->normalizeName($hteData['name']);
        if (HTE::where('normalized_name', $normalizedName)->exists()) {
            throw ValidationException::withMessages([
                'name' => ['This HTE already exists. Search and link the existing record instead.'],
            ]);
        }

        $hte = DB::transaction(function () use ($request, $programId, $hteData, $schedule, $batchSchedule, $departments, $normalizedName): HTE {
            $hte = HTE::create([
                ...$hteData,
                'normalized_name' => $normalizedName,
            ]);

            if ($schedule) {
                HteScheduleNormalizer::syncDays(
                    $hte,
                    $schedule['days'],
                    (int) ($schedule['grace_period_minutes'] ?? 10),
                );
            }

            DB::table('hte_programs')->updateOrInsert(
                ['hte_id' => $hte->id, 'program_id' => $programId],
                [
                    'linked_by' => $request->user()->id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            foreach ($departments as $department) {
                $this->persistDepartment(
                    $hte,
                    $department['department_name'],
                    $department['supervisor_full_name'],
                    $programId,
                    $request->user()->id,
                );
            }

            if ($batchSchedule) {
                $this->persistBatchSchedule(
                    $hte,
                    (int) $batchSchedule['academic_section_id'],
                    $batchSchedule['days'],
                    (int) ($batchSchedule['grace_period_minutes'] ?? 10),
                );
            }

            return $hte;
        });

        $hte->load(['moas.college', 'schedules', 'batchSchedules.academicSection.program', 'departments.supervisors.user']);
        $this->hydrateHteStudents($hte, $programId);

        return response()->json([
            'message' => $batchSchedule ? 'HTE record and batch schedule created successfully.' : 'HTE record created successfully.',
            'hte' => $this->htePayload($hte),
        ], 201);
    }

    public function update(Request $request, HTE $hte)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeHte($request->user(), $hte);
        $validated = $request->validate($this->hteRules($request, $hte));
        $schedule = $validated['schedule'] ?? null;
        unset($validated['schedule']);
        if ($schedule) {
            $this->ensureWorkingDay($schedule['days'], 'schedule.days');
            foreach ($this->legacyHteScheduleKeys() as $key) {
                unset($validated[$key]);
            }
        }
        $normalizedName = $this->normalizeName($validated['name']);

        if (HTE::where('normalized_name', $normalizedName)->whereKeyNot($hte->id)->exists()) {
            throw ValidationException::withMessages([
                'name' => ['Another HTE already uses this name.'],
            ]);
        }

        DB::transaction(function () use ($hte, $validated, $normalizedName, $schedule): void {
            $hte->update([...$validated, 'normalized_name' => $normalizedName]);
            if ($schedule) {
                HteScheduleNormalizer::syncDays(
                    $hte,
                    $schedule['days'],
                    (int) ($schedule['grace_period_minutes'] ?? 10),
                );
            }
        });

        $hte = $hte->fresh()->load(['moas.college', 'schedules']);
        $this->hydrateHteStudents($hte, $programId);

        return response()->json([
            'message' => 'HTE record updated successfully.',
            'hte' => $this->htePayload($hte),
        ]);
    }

    public function destroy(Request $request, HTE $hte)
    {
        ProgramAccess::authorizeHte($request->user(), $hte);
        $programId = ProgramAccess::programId($request->user());
        DB::table('hte_programs')
            ->where('hte_id', $hte->id)
            ->where('program_id', $programId)
            ->update(['is_active' => false, 'updated_at' => now()]);

        DB::table('hte_department_programs')
            ->where('program_id', $programId)
            ->whereIn('hte_department_id', $hte->departments()->select('id'))
            ->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json(['message' => 'HTE removed from this program. Shared HTE data, deployments, and MOA history were preserved.']);
    }

    public function batchSchedules(Request $request, HTE $hte)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeHte($request->user(), $hte);

        $schedules = $hte->batchSchedules()->get()->groupBy('academic_section_id');
        $batches = AcademicSection::with('program:id,code')
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->newestPeriodFirst()
            ->orderBy('year_level')
            ->orderBy('name')
            ->get()
            ->map(fn (AcademicSection $batch) => $this->batchSchedulePayload(
                $hte,
                $batch,
                $schedules->get($batch->id, collect()),
            ));

        return response()->json([
            'hte' => $hte->only(['id', 'name']),
            'batches' => $batches,
        ]);
    }

    public function saveBatchSchedule(Request $request, HTE $hte, AcademicSection $academicSection)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeHte($request->user(), $hte);
        abort_unless(
            (int) $academicSection->program_id === $programId && $academicSection->is_active,
            404,
        );

        $validated = $request->validate([
            'grace_period_minutes' => ['sometimes', 'integer', 'between:0,60'],
            ...$this->batchScheduleRules(),
        ]);
        $this->ensureWorkingDay($validated['days']);

        DB::transaction(function () use ($hte, $academicSection, $validated): void {
            $this->persistBatchSchedule($hte, $academicSection->id, $validated['days'], (int) ($validated['grace_period_minutes'] ?? 10));
        });

        return response()->json([
            'message' => 'Batch schedule saved successfully.',
            'batch' => $this->batchSchedulePayload(
                $hte,
                $academicSection->loadMissing('program:id,code'),
                $hte->batchSchedules()->where('academic_section_id', $academicSection->id)->get(),
            ),
        ]);
    }

    public function deploy(Request $request, Student $student)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeStudent($request->user(), $student);
        $validated = $request->validate([
            'hte_id' => ['nullable', 'exists:htes,id'],
            'hte_department_id' => ['nullable', 'exists:hte_departments,id'],
            'supervisor_record_id' => ['nullable', 'exists:supervisors,id'],
            'supervisor_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'supervisor')],
            'ojt_start_date' => ['nullable', 'date'],
            'ojt_end_date' => ['nullable', 'date', 'after_or_equal:ojt_start_date'],
            'required_ojt_hours' => ['required', 'numeric', 'between:1,2000'],
            'schedule_mode' => ['nullable', 'in:batch,custom'],
            'official_am_start' => ['nullable', 'required_if:schedule_mode,custom', 'required_without:schedule_mode', 'date_format:H:i'],
            'official_am_end' => ['nullable', 'required_if:schedule_mode,custom', 'required_without:schedule_mode', 'date_format:H:i', 'after:official_am_start'],
            'official_pm_start' => ['nullable', 'required_if:schedule_mode,custom', 'required_without:schedule_mode', 'date_format:H:i'],
            'official_pm_end' => ['nullable', 'required_if:schedule_mode,custom', 'required_without:schedule_mode', 'date_format:H:i', 'after:official_pm_start'],
            'work_days' => ['nullable', 'required_if:schedule_mode,custom', 'required_without:schedule_mode', 'array', 'min:1'],
            'work_days.*' => ['required', Rule::in($this->weekdays())],
            'internship_status' => ['required', 'in:pending,active,completed,dropped'],
            'allow_past_attendance' => ['required', 'boolean'],
        ]);
        $scheduleMode = $validated['schedule_mode'] ?? 'legacy';
        unset($validated['schedule_mode']);

        if (in_array($scheduleMode, ['custom', 'legacy'], true)) {
            $scheduleErrors = [];
            if (($validated['official_am_end'] ?? '00:00') >= '13:00') {
                $scheduleErrors['official_am_end'] = ['AM End must be earlier than 1:00 PM.'];
            }
            if (($validated['official_pm_start'] ?? '23:59') < '12:00') {
                $scheduleErrors['official_pm_start'] = ['PM Start must be 12:00 PM or later.'];
            }
            if ($scheduleErrors) {
                throw ValidationException::withMessages($scheduleErrors);
            }
        }

        if ($validated['hte_id'] && ! DB::table('hte_programs')
            ->where('hte_id', $validated['hte_id'])
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['hte_id' => ['The selected HTE is not available to this program.']]);
        }

        $department = isset($validated['hte_department_id'])
            ? HteDepartment::whereKey($validated['hte_department_id'])
                ->where('hte_id', $validated['hte_id'])
                ->whereHas('programs', fn ($query) => $query
                    ->whereKey($programId)
                    ->where('hte_department_programs.is_active', true))
                ->first()
            : null;
        if (($validated['hte_department_id'] ?? null) && ! $department) {
            throw ValidationException::withMessages(['hte_department_id' => ['The selected department is not available to this program and HTE.']]);
        }

        $supervisorRecord = isset($validated['supervisor_record_id'])
            ? Supervisor::whereKey($validated['supervisor_record_id'])
                ->where('hte_department_id', $department?->id)
                ->first()
            : null;
        if (($validated['supervisor_record_id'] ?? null) && ! $supervisorRecord) {
            throw ValidationException::withMessages(['supervisor_record_id' => ['The selected supervisor does not belong to this department.']]);
        }

        if ($supervisorRecord) {
            $validated['supervisor_id'] = $supervisorRecord->user_id;
        }

        if (! $validated['hte_id']) {
            $validated['hte_department_id'] = null;
            $validated['supervisor_record_id'] = null;
            $validated['supervisor_id'] = null;
        }

        if ($scheduleMode === 'batch' && $validated['hte_id']) {
            $batchRows = HteBatchSchedule::query()
                ->where('hte_id', $validated['hte_id'])
                ->where('academic_section_id', $student->academic_section_id)
                ->get()
                ->keyBy('day_of_week');
            $workingDays = collect($this->weekdays())
                ->filter(fn (string $day) => (bool) $batchRows->get($day)?->is_working)
                ->values();
            $template = $workingDays->map(fn (string $day) => $batchRows->get($day))->filter()->first();

            if (! $student->academic_section_id || ! $template) {
                throw ValidationException::withMessages([
                    'schedule_mode' => ['Configure and save this HTE batch schedule before using it for student deployment.'],
                ]);
            }

            $validated = [
                ...$validated,
                'official_am_start' => $this->shortTime($template->am_start, '08:00'),
                'official_am_end' => $this->shortTime($template->am_end, '12:00'),
                'official_pm_start' => $this->shortTime($template->pm_start, '13:00'),
                'official_pm_end' => $this->shortTime($template->pm_end, '17:00'),
                'work_days' => $workingDays->all(),
            ];
        }

        if ($validated['hte_id']) {
            $student->placementAssignedBy = $request->user()->id;
            $student->update([
                ...$validated,
                'schedule_status' => 'pending',
            ]);
            if ($scheduleMode === 'batch') {
                StudentRecordNormalizer::clearScheduleOverrides($student->fresh());
            } else {
                StudentRecordNormalizer::markScheduleOverridesManual($student->fresh());
            }
            ApprovalRecord::submit(
                ApprovalRecord::TYPE_DEPLOYMENT,
                $student->id,
                $request->user()->id,
            );
            User::query()->where('role', 'program_head')->where('is_active', true)->where('program_id', $programId)->pluck('id')
                ->each(fn (int $userId) => SystemNotification::sendToUser($userId, 'Deployment request submitted', $student->student_id.' deployment is ready for Program Head review.', 'approval', '/program-head/reports'));
        } else {
            $student->placementAssignedBy = $request->user()->id;
            $student->update([
                ...$validated,
                'schedule_status' => 'pending',
            ]);
            StudentRecordNormalizer::clearScheduleOverrides($student->fresh());

            ApprovalRecord::query()
                ->where('subject_type', ApprovalRecord::TYPE_DEPLOYMENT)
                ->where('subject_id', $student->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'remarks' => 'Deployment request withdrawn by the coordinator.',
                    'decided_by' => $request->user()->id,
                    'decided_at' => now(),
                ]);
        }

        return response()->json([
            'message' => $validated['hte_id'] ? 'Student deployment and schedule updated.' : 'Student undeployed successfully.',
            'student' => $student->fresh()->load([
                'user', 'college', 'program', 'hte', 'hteDepartment',
                'supervisor:id,name,email', 'supervisorRecord.user:id,name,email,user_id,is_active',
                'currentInternship.scheduleOverrides',
            ]),
        ]);
    }

    public function storeHoliday(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate($this->holidayRules($programId));
        $hteIds = $validated['hte_ids'] ?? [];
        unset($validated['hte_ids']);

        $holiday = DB::transaction(function () use ($request, $programId, $validated, $hteIds) {
            $holiday = Holiday::create([
                ...$validated,
                'college_id' => $request->user()->college_id,
                'program_id' => $programId,
            ]);
            $holiday->htes()->sync($holiday->holiday_type === 'local_holiday' ? $hteIds : []);

            return $holiday->load('htes:id,name');
        });

        return response()->json(['message' => 'Holiday added successfully.', 'holiday' => $holiday], 201);
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        ProgramAccess::authorizeHoliday($request->user(), $holiday);
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate($this->holidayRules($programId));
        $hteIds = $validated['hte_ids'] ?? [];
        unset($validated['hte_ids']);

        DB::transaction(function () use ($holiday, $validated, $hteIds) {
            $holiday->update($validated);
            $holiday->htes()->sync($holiday->holiday_type === 'local_holiday' ? $hteIds : []);
        });

        return response()->json(['message' => 'Holiday updated successfully.', 'holiday' => $holiday->fresh()->load('htes:id,name')]);
    }

    public function destroyHoliday(Request $request, Holiday $holiday)
    {
        ProgramAccess::authorizeHoliday($request->user(), $holiday);
        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted successfully.']);
    }

    public function storeMoa(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate([
            'hte_id' => ['required', Rule::exists('hte_programs', 'hte_id')->where(fn ($query) => $query->where('program_id', $programId)->where('is_active', true))],
            'effective_date' => ['required', 'date'],
            'expiration_date' => ['required', 'date', 'after:effective_date'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $path = $request->file('file')->store('moas', 'local');

        $moa = MOA::create([
            ...collect($validated)->except('file')->all(),
            'college_id' => $request->user()->college_id,
            'program_id' => $programId,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'MOA uploaded and submitted for approval.',
            'moa' => $this->moaPayload($moa->load(['hte', 'college', 'approver'])),
        ], 201);
    }

    public function updateMoa(Request $request, MOA $moa)
    {
        ProgramAccess::authorizeMoa($request->user(), $moa);
        $validated = $request->validate([
            'effective_date' => ['required', 'date'],
            'expiration_date' => ['required', 'date', 'after:effective_date'],
        ]);
        $moa->update([
            ...$validated,
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return response()->json([
            'message' => 'MOA details updated and returned to pending approval.',
            'moa' => $this->moaPayload($moa->fresh()->load(['hte', 'college', 'approver'])),
        ]);
    }

    public function downloadMoa(Request $request, MOA $moa)
    {
        ProgramAccess::authorizeMoa($request->user(), $moa);

        $path = $this->privateStoragePath($moa->file_path);

        if (! $path || ! $this->privateDisk()->exists($path)) {
            return response()->json(['message' => 'MOA file not found.'], 404);
        }

        $moa->loadMissing('hte:id,name');
        $hteName = Str::slug($moa->hte?->name ?? 'agreement') ?: 'agreement';

        return $this->privateDisk()->download($path, "MOA-{$hteName}-{$moa->id}.pdf");
    }

    private function hteRules(Request $request, ?HTE $hte = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'digits:11'],
            'latitude' => ['nullable', 'required_if:geofence_enabled,true', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:geofence_enabled,true', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['required', 'integer', 'between:10,10000'],
            'geofence_enabled' => ['required', 'boolean'],
            'default_am_start' => ['nullable', 'required_without:schedule', 'date_format:H:i'],
            'default_am_end' => ['nullable', 'required_without:schedule', 'date_format:H:i', 'after:default_am_start'],
            'default_pm_start' => ['nullable', 'required_without:schedule', 'date_format:H:i'],
            'default_pm_end' => ['nullable', 'required_without:schedule', 'date_format:H:i', 'after:default_pm_start'],
            'work_days' => ['nullable', 'required_without:schedule', 'array', 'min:1'],
            'work_days.*' => ['required', Rule::in($this->weekdays())],
            'schedule' => ['nullable', 'required_without:work_days', 'array'],
            'schedule.grace_period_minutes' => ['sometimes', 'integer', 'between:0,60'],
            'schedule.days' => ['required_with:schedule', 'array', 'required_array_keys:'.implode(',', $this->weekdays())],
            'is_active' => ['required', 'boolean'],
        ];

        foreach ($this->weekdays() as $day) {
            $prefix = "schedule.days.{$day}";
            $rules[$prefix] = ['required_with:schedule', 'array'];
            $rules["{$prefix}.is_working"] = ['required_with:schedule', 'boolean'];
            $rules["{$prefix}.am_start"] = ['nullable', "required_if:{$prefix}.is_working,true", 'date_format:H:i'];
            $rules["{$prefix}.am_end"] = ['nullable', "required_if:{$prefix}.is_working,true", 'date_format:H:i', "after:{$prefix}.am_start"];
            $rules["{$prefix}.pm_start"] = ['nullable', "required_if:{$prefix}.is_working,true", 'date_format:H:i'];
            $rules["{$prefix}.pm_end"] = ['nullable', "required_if:{$prefix}.is_working,true", 'date_format:H:i', "after:{$prefix}.pm_start"];
        }

        return $rules;
    }

    private function holidayRules(int $programId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'holiday_type' => ['required', Rule::in(Holiday::TYPES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'official_reference' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'affected_area' => ['nullable', 'required_if:holiday_type,local_holiday', 'string', 'max:255'],
            'hte_ids' => ['exclude_unless:holiday_type,local_holiday', 'required', 'array', 'min:1'],
            'hte_ids.*' => ['integer', Rule::exists('hte_programs', 'hte_id')->where(fn ($query) => $query
                ->where('program_id', $programId)
                ->where('is_active', true))],
            'is_recurring' => ['required', 'boolean'],
        ];
    }

    private function batchScheduleRules(string $daysPrefix = 'days'): array
    {
        $rules = [
            $daysPrefix => ['required', 'array', 'required_array_keys:'.implode(',', $this->weekdays())],
        ];

        foreach ($this->weekdays() as $day) {
            $prefix = "{$daysPrefix}.{$day}";
            $rules[$prefix] = ['required', 'array'];
            $rules["{$prefix}.is_working"] = ['required', 'boolean'];
            $rules["{$prefix}.am_start"] = ['required', 'date_format:H:i'];
            $rules["{$prefix}.am_end"] = ['required', 'date_format:H:i', "after:{$prefix}.am_start"];
            $rules["{$prefix}.pm_start"] = ['required', 'date_format:H:i'];
            $rules["{$prefix}.pm_end"] = ['required', 'date_format:H:i', "after:{$prefix}.pm_start"];
        }

        return $rules;
    }

    private function ensureWorkingDay(array $days, string $field = 'days'): void
    {
        if (! collect($days)->contains(fn (array $schedule) => (bool) $schedule['is_working'])) {
            throw ValidationException::withMessages([
                $field => 'Select at least one working day.',
            ]);
        }
    }

    private function persistBatchSchedule(HTE $hte, int $academicSectionId, array $days, int $gracePeriodMinutes): void
    {
        foreach ($this->weekdays() as $day) {
            HteBatchSchedule::updateOrCreate(
                [
                    'hte_id' => $hte->id,
                    'academic_section_id' => $academicSectionId,
                    'day_of_week' => $day,
                ],
                [...$days[$day], 'grace_period_minutes' => $gracePeriodMinutes],
            );
        }
    }

    private function weekdays(): array
    {
        return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    }

    private function normalizeName(string $value): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim($value)));
    }

    private function persistDepartment(
        HTE $hte,
        string $departmentName,
        string $supervisorName,
        int $programId,
        int $linkedBy,
    ): array {
        $departmentName = preg_replace('/\s+/', ' ', trim($departmentName));
        $supervisorName = preg_replace('/\s+/', ' ', trim($supervisorName));

        $department = HteDepartment::firstOrCreate(
            [
                'hte_id' => $hte->id,
                'normalized_name' => $this->normalizeName($departmentName),
            ],
            ['department_name' => $departmentName],
        );

        $this->linkDepartmentToProgram($department->id, $programId, $linkedBy);

        $supervisor = Supervisor::firstOrCreate(
            [
                'hte_department_id' => $department->id,
                'normalized_name' => $this->normalizeName($supervisorName),
            ],
            ['full_name' => $supervisorName],
        );

        return [$department, $supervisor];
    }

    private function linkDepartmentToProgram(int $departmentId, int $programId, int $linkedBy): void
    {
        $existing = DB::table('hte_department_programs')
            ->where('hte_department_id', $departmentId)
            ->where('program_id', $programId)
            ->first();

        DB::table('hte_department_programs')->updateOrInsert(
            ['hte_department_id' => $departmentId, 'program_id' => $programId],
            [
                'linked_by' => $linkedBy,
                'is_active' => true,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );
    }

    private function htePayload(HTE $hte): array
    {
        $moas = $hte->relationLoaded('moas') ? $hte->moas : collect();
        $students = $hte->relationLoaded('students') ? $hte->students : collect();

        return [
            ...$hte->toArray(),
            'schedule' => $this->hteSchedulePayload($hte),
            'students_count' => $hte->students_count ?? $students->count(),
            'active_students_count' => $students->where('internship_status', 'active')->count(),
            'valid_moa_count' => $moas->filter(fn (MOA $moa) => $moa->status === 'approved' && $moa->expiration_date->gte(now()->startOfDay()))->count(),
            'batch_schedule_summary' => $this->batchScheduleSummary($hte),
        ];
    }

    private function hydrateHteStudents(HTE $hte, int $programId): void
    {
        $students = Student::with(['user', 'program', 'college'])
            ->forProgram($programId)
            ->forHte($hte->id)
            ->orderBy('last_name')
            ->get();

        $hte->setRelation('students', $students);
        $hte->setAttribute('students_count', $students->count());
    }

    private function batchScheduleSummary(HTE $hte): ?array
    {
        if (! $hte->relationLoaded('batchSchedules') || $hte->batchSchedules->isEmpty()) {
            return null;
        }

        $schedules = $hte->batchSchedules;
        $latestBatchId = $schedules->sortByDesc('updated_at')->first()?->academic_section_id;
        $batchRows = $schedules->where('academic_section_id', $latestBatchId)->keyBy('day_of_week');
        $batch = $batchRows->first()?->academicSection;
        $groups = [];

        foreach ($this->weekdays() as $day) {
            $row = $batchRows->get($day);
            if (! $row || ! $row->is_working) {
                continue;
            }

            $times = [
                'am_start' => $this->shortTime($row->am_start, ''),
                'am_end' => $this->shortTime($row->am_end, ''),
                'pm_start' => $this->shortTime($row->pm_start, ''),
                'pm_end' => $this->shortTime($row->pm_end, ''),
            ];
            $key = implode('|', $times);

            if (! isset($groups[$key])) {
                $groups[$key] = [...$times, 'days' => []];
            }

            $groups[$key]['days'][] = $day;
        }

        return [
            'batch_id' => $batch?->id,
            'grace_period_minutes' => (int) ($batchRows->first()?->grace_period_minutes ?? 10),
            'batch_label' => $batch ? sprintf(
                '%s · Year %s-%s · AY %s',
                $batch->program?->code ?? 'Program',
                $batch->year_level,
                $batch->name,
                $batch->academic_year,
            ) : 'Saved batch schedule',
            'configured_batches' => $schedules->pluck('academic_section_id')->unique()->count(),
            'groups' => array_values($groups),
        ];
    }

    private function batchSchedulePayload(HTE $hte, AcademicSection $batch, $scheduleRows): array
    {
        $rows = collect($scheduleRows)->keyBy('day_of_week');
        $hteRows = ($hte->relationLoaded('schedules') ? $hte->schedules : $hte->schedules()->get())
            ->keyBy('day_of_week');

        return [
            'id' => $batch->id,
            'grace_period_minutes' => (int) ($rows->first()?->grace_period_minutes ?? 10),
            'name' => $batch->name,
            'year_level' => $batch->year_level,
            'academic_year' => $batch->academic_year,
            'label' => sprintf(
                '%s · Year %s-%s · AY %s',
                $batch->program?->code ?? 'Program',
                $batch->year_level,
                $batch->name,
                $batch->academic_year,
            ),
            'has_saved_schedule' => $rows->isNotEmpty(),
            'days' => collect($this->weekdays())->mapWithKeys(function (string $day) use ($rows, $hteRows): array {
                $row = $rows->get($day);
                $hteRow = $hteRows->get($day);

                return [$day => $row ? [
                    'is_working' => $row->is_working,
                    'am_start' => $this->shortTime($row->am_start),
                    'am_end' => $this->shortTime($row->am_end),
                    'pm_start' => $this->shortTime($row->pm_start),
                    'pm_end' => $this->shortTime($row->pm_end),
                ] : [
                    'is_working' => (bool) $hteRow?->is_working,
                    'am_start' => $this->shortTime($hteRow?->am_start),
                    'am_end' => $this->shortTime($hteRow?->am_end),
                    'pm_start' => $this->shortTime($hteRow?->pm_start),
                    'pm_end' => $this->shortTime($hteRow?->pm_end),
                ]];
            })->all(),
        ];
    }

    private function hteSchedulePayload(HTE $hte): array
    {
        $rows = ($hte->relationLoaded('schedules') ? $hte->schedules : $hte->schedules()->get())
            ->keyBy('day_of_week');

        return [
            'configured' => $rows->count() === 7,
            'grace_period_minutes' => $rows->first()?->grace_period_minutes,
            'days' => collect($this->weekdays())->mapWithKeys(function (string $day) use ($rows): array {
                $row = $rows->get($day);

                return [$day => [
                    'is_working' => (bool) $row?->is_working,
                    'am_start' => $this->shortTime($row?->am_start),
                    'am_end' => $this->shortTime($row?->am_end),
                    'pm_start' => $this->shortTime($row?->pm_start),
                    'pm_end' => $this->shortTime($row?->pm_end),
                ]];
            })->all(),
        ];
    }

    private function legacyHteScheduleKeys(): array
    {
        return ['default_am_start', 'default_am_end', 'default_pm_start', 'default_pm_end', 'work_days'];
    }

    private function shortTime($value, ?string $fallback = null): ?string
    {
        return $value ? substr((string) $value, 0, 5) : $fallback;
    }

    private function moaPayload(MOA $moa): array
    {
        $expired = $moa->expiration_date->lt(now()->startOfDay());

        return [
            ...$moa->toArray(),
            'computed_status' => $expired ? 'expired' : $moa->status,
            'days_remaining' => (int) now()->startOfDay()->diffInDays($moa->expiration_date, false),
        ];
    }

    private function moaAlert(MOA $moa, $today): array
    {
        $days = (int) $today->diffInDays($moa->expiration_date, false);

        return [
            'id' => $moa->id,
            'hte' => $moa->hte?->name,
            'college' => $moa->college?->code,
            'expiration_date' => $moa->expiration_date->toDateString(),
            'days_remaining' => $days,
            'level' => $days < 0 ? 'expired' : ($days <= 30 ? 'critical' : 'warning'),
        ];
    }
}
