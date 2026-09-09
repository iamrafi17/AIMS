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
use App\Support\ProgramAccess;
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
                'students' => fn ($query) => $query->where('program_id', $programId),
                'students.program',
                'students.college',
                'moas' => fn ($query) => $query->where('program_id', $programId),
                'moas.college',
                'moas.approver',
                'batchSchedules' => fn ($query) => $query->whereHas(
                    'academicSection',
                    fn ($sectionQuery) => $sectionQuery->where('program_id', $programId),
                ),
                'batchSchedules.academicSection.program',
                'departments.supervisors.user:id,name,email,user_id,is_active',
            ])
            ->withCount(['students' => fn ($query) => $query->where('program_id', $programId)])
            ->orderBy('name')
            ->get();
        $moas = MOA::with(['hte', 'college', 'program', 'approver'])
            ->where('program_id', $programId)
            ->latest('expiration_date')->get();
        $today = now()->startOfDay();
        $validMoas = $moas->filter(fn (MOA $moa) => $moa->status === 'approved' && $moa->expiration_date->gte($today));

        return response()->json([
            'htes' => $htes->map(fn (HTE $hte) => $this->htePayload($hte)),
            'deployments' => Student::with([
                'user', 'college', 'program', 'hte', 'hteDepartment',
                'supervisor:id,name,email', 'supervisorRecord.user:id,name,email,user_id,is_active',
            ])
                ->where('program_id', $programId)
                ->where('registration_status', 'approved')
                ->orderBy('last_name')
                ->get(),
            'holidays' => Holiday::where('program_id', $programId)->orderByDesc('date')->get(),
            'moas' => $moas->map(fn (MOA $moa) => $this->moaPayload($moa)),
            'colleges' => College::whereKey($request->user()->college_id)->where('is_active', true)->get(['id', 'name', 'code']),
            'batches' => AcademicSection::with('program:id,code')
                ->where('program_id', $programId)
                ->where('is_active', true)
                ->orderByDesc('academic_year')
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
        ProgramAccess::authorizeHte($request->user(), $hte);

        return response()->json($this->htePayload(
            $hte->load([
                'students' => fn ($query) => $query->where('program_id', ProgramAccess::programId($request->user())),
                'students.user', 'students.program', 'students.college',
                'moas' => fn ($query) => $query->where('program_id', ProgramAccess::programId($request->user())),
                'moas.college', 'moas.approver', 'departments.supervisors.user:id,name,email,user_id,is_active',
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
            ->with(['departments.supervisors.user:id,name,email,user_id,is_active'])
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

        DB::table('hte_programs')->updateOrInsert(
            ['hte_id' => $hte->id, 'program_id' => $programId],
            [
                'college_id' => $request->user()->college_id,
                'linked_by' => $request->user()->id,
                'is_active' => true,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        return response()->json([
            'message' => $existing?->is_active
                ? 'HTE is already available to this program.'
                : 'Existing HTE, departments, and supervisors linked to your program.',
            'hte' => $this->htePayload($hte->fresh()->load('departments.supervisors.user:id,name,email,user_id,is_active')),
        ]);
    }

    public function storeDepartment(Request $request, HTE $hte)
    {
        ProgramAccess::authorizeHte($request->user(), $hte);
        $validated = $request->validate([
            'department_name' => ['required', 'string', 'max:255'],
            'supervisor_full_name' => ['required', 'string', 'max:255'],
        ]);

        [$department, $supervisor] = DB::transaction(fn () => $this->persistDepartment(
            $hte,
            $validated['department_name'],
            $validated['supervisor_full_name'],
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
        unset($validated['departments']);
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

        $hte = DB::transaction(function () use ($request, $programId, $hteData, $batchSchedule, $departments, $normalizedName): HTE {
            $hte = HTE::create([
                ...$hteData,
                'college_id' => $request->user()->college_id,
                'program_id' => $programId,
                'normalized_name' => $normalizedName,
            ]);

            DB::table('hte_programs')->updateOrInsert(
                ['hte_id' => $hte->id, 'program_id' => $programId],
                [
                    'college_id' => $request->user()->college_id,
                    'linked_by' => $request->user()->id,
                    'is_active' => true,
                    'updated_at' => now(),
                ],
            );

            foreach ($departments as $department) {
                $this->persistDepartment(
                    $hte,
                    $department['department_name'],
                    $department['supervisor_full_name'],
                );
            }

            if ($batchSchedule) {
                $this->persistBatchSchedule(
                    $hte,
                    (int) $batchSchedule['academic_section_id'],
                    $batchSchedule['days'],
                );
            }

            return $hte;
        });

        return response()->json([
            'message' => $batchSchedule ? 'HTE record and batch schedule created successfully.' : 'HTE record created successfully.',
            'hte' => $this->htePayload($hte->load(['students.program', 'students.college', 'moas.college', 'batchSchedules.academicSection.program', 'departments.supervisors.user'])),
        ], 201);
    }

    public function update(Request $request, HTE $hte)
    {
        ProgramAccess::authorizeHte($request->user(), $hte);
        $validated = $request->validate($this->hteRules($request, $hte));
        $normalizedName = $this->normalizeName($validated['name']);

        if (HTE::where('normalized_name', $normalizedName)->whereKeyNot($hte->id)->exists()) {
            throw ValidationException::withMessages([
                'name' => ['Another HTE already uses this name.'],
            ]);
        }

        $hte->update([...$validated, 'normalized_name' => $normalizedName]);

        return response()->json([
            'message' => 'HTE record updated successfully.',
            'hte' => $this->htePayload($hte->fresh()->load(['students.program', 'students.college', 'moas.college'])),
        ]);
    }

    public function destroy(Request $request, HTE $hte)
    {
        ProgramAccess::authorizeHte($request->user(), $hte);
        DB::table('hte_programs')
            ->where('hte_id', $hte->id)
            ->where('program_id', ProgramAccess::programId($request->user()))
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
            ->orderByDesc('academic_year')
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

        $validated = $request->validate($this->batchScheduleRules());
        $this->ensureWorkingDay($validated['days']);

        DB::transaction(function () use ($hte, $academicSection, $validated): void {
            $this->persistBatchSchedule($hte, $academicSection->id, $validated['days']);
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
            'official_am_start' => ['required', 'date_format:H:i'],
            'official_am_end' => ['required', 'date_format:H:i', 'after:official_am_start'],
            'official_pm_start' => ['required', 'date_format:H:i'],
            'official_pm_end' => ['required', 'date_format:H:i', 'after:official_pm_start'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['required', Rule::in($this->weekdays())],
            'internship_status' => ['required', 'in:pending,active,completed,dropped'],
            'allow_past_attendance' => ['required', 'boolean'],
        ]);

        if ($validated['hte_id'] && ! DB::table('hte_programs')
            ->where('hte_id', $validated['hte_id'])
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['hte_id' => ['The selected HTE is not available to this program.']]);
        }

        $department = isset($validated['hte_department_id'])
            ? HteDepartment::whereKey($validated['hte_department_id'])->where('hte_id', $validated['hte_id'])->first()
            : null;
        if (($validated['hte_department_id'] ?? null) && ! $department) {
            throw ValidationException::withMessages(['hte_department_id' => ['The selected department does not belong to this HTE.']]);
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

        if ($validated['hte_id']) {
            $student->update([
                ...$validated,
                'schedule_status' => 'pending',
            ]);
            ApprovalRecord::submit(
                ApprovalRecord::TYPE_DEPLOYMENT,
                $student->id,
                $request->user()->id,
            );
            User::query()->where('role', 'program_head')->where('is_active', true)->where('program_id', $programId)->pluck('id')
                ->each(fn (int $userId) => SystemNotification::sendToUser($userId, 'Deployment request submitted', $student->student_id.' deployment is ready for Program Head review.', 'approval', '/program-head/reports'));
        } else {
            $student->update([
                ...$validated,
                'schedule_status' => 'pending',
            ]);

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
            ]),
        ]);
    }

    public function storeHoliday(Request $request)
    {
        $holiday = Holiday::create([
            ...$request->validate($this->holidayRules()),
            'college_id' => $request->user()->college_id,
            'program_id' => ProgramAccess::programId($request->user()),
        ]);

        return response()->json(['message' => 'Holiday added successfully.', 'holiday' => $holiday], 201);
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        ProgramAccess::authorizeHoliday($request->user(), $holiday);
        $holiday->update($request->validate($this->holidayRules()));

        return response()->json(['message' => 'Holiday updated successfully.', 'holiday' => $holiday->fresh()]);
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'digits:11'],
            'latitude' => ['nullable', 'required_if:geofence_enabled,true', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:geofence_enabled,true', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['required', 'integer', 'between:10,10000'],
            'geofence_enabled' => ['required', 'boolean'],
            'default_am_start' => ['required', 'date_format:H:i'],
            'default_am_end' => ['required', 'date_format:H:i', 'after:default_am_start'],
            'default_pm_start' => ['required', 'date_format:H:i'],
            'default_pm_end' => ['required', 'date_format:H:i', 'after:default_pm_start'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['required', Rule::in($this->weekdays())],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function holidayRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
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

    private function persistBatchSchedule(HTE $hte, int $academicSectionId, array $days): void
    {
        foreach ($this->weekdays() as $day) {
            HteBatchSchedule::updateOrCreate(
                [
                    'hte_id' => $hte->id,
                    'academic_section_id' => $academicSectionId,
                    'day_of_week' => $day,
                ],
                $days[$day],
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

    private function persistDepartment(HTE $hte, string $departmentName, string $supervisorName): array
    {
        $departmentName = preg_replace('/\s+/', ' ', trim($departmentName));
        $supervisorName = preg_replace('/\s+/', ' ', trim($supervisorName));

        $department = HteDepartment::firstOrCreate(
            [
                'hte_id' => $hte->id,
                'normalized_name' => $this->normalizeName($departmentName),
            ],
            ['department_name' => $departmentName],
        );

        $supervisor = Supervisor::firstOrCreate(
            [
                'hte_department_id' => $department->id,
                'normalized_name' => $this->normalizeName($supervisorName),
            ],
            ['full_name' => $supervisorName],
        );

        return [$department, $supervisor];
    }

    private function htePayload(HTE $hte): array
    {
        $moas = $hte->relationLoaded('moas') ? $hte->moas : collect();
        $students = $hte->relationLoaded('students') ? $hte->students : collect();

        return [
            ...$hte->toArray(),
            'students_count' => $hte->students_count ?? $students->count(),
            'active_students_count' => $students->where('internship_status', 'active')->count(),
            'valid_moa_count' => $moas->filter(fn (MOA $moa) => $moa->status === 'approved' && $moa->expiration_date->gte(now()->startOfDay()))->count(),
            'batch_schedule_summary' => $this->batchScheduleSummary($hte),
        ];
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
        $defaults = [
            'am_start' => $this->shortTime($hte->default_am_start, '08:00'),
            'am_end' => $this->shortTime($hte->default_am_end, '12:00'),
            'pm_start' => $this->shortTime($hte->default_pm_start, '13:00'),
            'pm_end' => $this->shortTime($hte->default_pm_end, '17:00'),
        ];

        return [
            'id' => $batch->id,
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
            'days' => collect($this->weekdays())->mapWithKeys(function (string $day) use ($rows, $defaults): array {
                $row = $rows->get($day);

                return [$day => $row ? [
                    'is_working' => $row->is_working,
                    'am_start' => $this->shortTime($row->am_start, $defaults['am_start']),
                    'am_end' => $this->shortTime($row->am_end, $defaults['am_end']),
                    'pm_start' => $this->shortTime($row->pm_start, $defaults['pm_start']),
                    'pm_end' => $this->shortTime($row->pm_end, $defaults['pm_end']),
                ] : [
                    'is_working' => in_array($day, $hte->work_days ?? $this->weekdays(), true),
                    ...$defaults,
                ]];
            })->all(),
        ];
    }

    private function shortTime($value, string $fallback): string
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
