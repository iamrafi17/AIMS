<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\College;
use App\Models\Holiday;
use App\Models\HTE;
use App\Models\MOA;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\User;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorHTEController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $htes = HTE::where('program_id', $programId)
            ->with([
                'students' => fn ($query) => $query->where('program_id', $programId),
                'students.program',
                'students.college',
                'moas' => fn ($query) => $query->where('program_id', $programId),
                'moas.college',
                'moas.approver',
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
            'deployments' => Student::with(['user', 'college', 'program', 'hte', 'supervisor:id,name,email'])
                ->where('program_id', $programId)
                ->where('registration_status', 'approved')
                ->orderBy('last_name')
                ->get(),
            'holidays' => Holiday::where('program_id', $programId)->orderByDesc('date')->get(),
            'moas' => $moas->map(fn (MOA $moa) => $this->moaPayload($moa)),
            'colleges' => College::whereKey($request->user()->college_id)->where('is_active', true)->get(['id', 'name', 'code']),
            'supervisors' => User::where('role', 'supervisor')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
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
            $hte->load(['students.user', 'students.program', 'students.college', 'moas.college', 'moas.approver'])
        ));
    }

    public function store(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $hte = HTE::create([
            ...$request->validate($this->hteRules($request)),
            'college_id' => $request->user()->college_id,
            'program_id' => $programId,
        ]);

        return response()->json([
            'message' => 'HTE record created successfully.',
            'hte' => $this->htePayload($hte->load(['students.program', 'students.college', 'moas.college'])),
        ], 201);
    }

    public function update(Request $request, HTE $hte)
    {
        ProgramAccess::authorizeHte($request->user(), $hte);
        $hte->update($request->validate($this->hteRules($request, $hte)));

        return response()->json([
            'message' => 'HTE record updated successfully.',
            'hte' => $this->htePayload($hte->fresh()->load(['students.program', 'students.college', 'moas.college'])),
        ]);
    }

    public function destroy(Request $request, HTE $hte)
    {
        ProgramAccess::authorizeHte($request->user(), $hte);
        if ($hte->students()->exists()) {
            throw ValidationException::withMessages(['hte' => 'Reassign or undeploy all students before deleting this HTE.']);
        }

        $paths = $hte->moas()->pluck('file_path')
            ->map(fn ($path) => $this->publicStoragePath($path))
            ->filter();
        $hte->delete();
        $paths->each(fn (string $path) => $this->publicDisk()->delete($path));

        return response()->json(['message' => 'HTE record deleted successfully.']);
    }

    public function deploy(Request $request, Student $student)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramAccess::authorizeStudent($request->user(), $student);
        $validated = $request->validate([
            'hte_id' => ['nullable', Rule::exists('htes', 'id')->where('program_id', $programId)],
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
            SystemNotification::sendToRole('vpaa', 'Deployment request submitted', $student->student_id.' deployment is ready for final review.', 'approval', '/vpaa/approvals');
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
            'student' => $student->fresh()->load(['user', 'college', 'program', 'hte', 'supervisor:id,name,email']),
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
            'hte_id' => ['required', Rule::exists('htes', 'id')->where('program_id', $programId)],
            'college_id' => ['nullable', Rule::in([(int) $request->user()->college_id])],
            'effective_date' => ['required', 'date'],
            'expiration_date' => ['required', 'date', 'after:effective_date'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $path = $request->file('file')->store('moas', 'public');

        $moa = MOA::create([
            ...collect($validated)->except(['file', 'college_id'])->all(),
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

        $path = $this->publicStoragePath($moa->file_path);

        if (! $path || ! $this->publicDisk()->exists($path)) {
            return response()->json(['message' => 'MOA file not found.'], 404);
        }

        $moa->loadMissing('hte:id,name');
        $hteName = Str::slug($moa->hte?->name ?? 'agreement') ?: 'agreement';

        return $this->publicDisk()->download($path, "MOA-{$hteName}-{$moa->id}.pdf");
    }

    public function destroyMoa(Request $request, MOA $moa)
    {
        ProgramAccess::authorizeMoa($request->user(), $moa);
        $path = $this->publicStoragePath($moa->file_path);
        if ($path) {
            $this->publicDisk()->delete($path);
        }
        $moa->delete();

        return response()->json(['message' => 'MOA record deleted successfully.']);
    }

    private function hteRules(Request $request, ?HTE $hte = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('htes', 'name')->where('program_id', ProgramAccess::programId($request->user()))->ignore($hte?->id)],
            'address' => ['required', 'string', 'max:1000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
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

    private function weekdays(): array
    {
        return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
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
        ];
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
