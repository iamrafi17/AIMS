<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\GeneratedReport;
use App\Models\Program;
use App\Models\ReportTemplate;
use App\Services\ReportTemplateDatasetService;
use App\Services\ReportTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportTemplateController extends Controller
{
    public function __construct(
        private readonly ReportTemplateService $templates,
        private readonly ReportTemplateDatasetService $datasets,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeTemplateRole($request);
        $user = $request->user();
        $reportTypes = ReportTemplateService::reportTypesForRole($user->role);
        $query = ReportTemplate::query()
            ->with(['program:id,name,code', 'academicPeriod:id,academic_year,semester', 'uploader:id'])
            ->whereIn('report_type', [...array_keys($reportTypes), ReportTemplateService::SHARED_REPORT_TYPE]);

        if ($user->role === 'coordinator') {
            $query->where('program_id', $user->program_id);
        } elseif ($user->role === 'program_head') {
            $query->where(fn ($scope) => $scope->where('program_id', $user->program_id)->orWhereNull('program_id'));
        }

        if ($request->filled('report_type')) {
            $request->validate(['report_type' => ['string', Rule::in(array_keys($reportTypes))]]);
            $query->whereIn('report_type', [$request->string('report_type'), ReportTemplateService::SHARED_REPORT_TYPE]);
        }

        return response()->json([
            'templates' => $query->latest()->get()->map(fn (ReportTemplate $template) => $this->resource($template))->values(),
            'report_types' => collect($reportTypes)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'programs' => $this->availablePrograms($request),
            'can_manage' => in_array($user->role, ['coordinator', 'program_head', 'vpaa'], true),
            'can_generate' => in_array($user->role, ['coordinator', 'program_head', 'vpaa'], true),
            'assigned_program_id' => $user->program_id,
            'semesters' => ['First Semester', 'Second Semester', 'Summer'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        if (in_array($request->user()->role, ['coordinator', 'program_head'], true)) {
            $request->merge(['program_id' => $request->user()->program_id]);
        } elseif ($request->user()->role === 'vpaa') {
            $request->merge(['program_id' => null]);
        }
        $validated = $request->validate([
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'report_type' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'activate' => ['sometimes', 'boolean'],
            'file' => [
                'required', 'file', 'max:15360',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (strtolower((string) $value->getClientOriginalExtension()) !== 'docx') {
                        $fail('Only DOCX report templates are allowed.');
                    }
                },
            ],
        ]);
        $reportType = ReportTemplateService::SHARED_REPORT_TYPE;
        $programId = in_array($request->user()->role, ['coordinator', 'program_head'], true)
            ? (int) $request->user()->program_id
            : ($validated['program_id'] ?? null);
        if (in_array($request->user()->role, ['coordinator', 'program_head'], true) && ! $programId) {
            abort(403, 'You must be assigned to a program before uploading report templates.');
        }

        $upload = $request->file('file');
        $checksum = hash_file('sha256', $upload->getRealPath());
        $duplicate = ReportTemplate::query()
            ->forTemplateScope($programId, $reportType, ReportTemplate::SCOPE_ALL_PERIODS)
            ->where('checksum', $checksum)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['file' => ['This exact file is already the uploaded official template.']]);
        }

        $originalName = Str::limit(basename(str_replace('\\', '/', $upload->getClientOriginalName())), 255, '');
        $storedName = Str::uuid().'.docx';
        $path = $upload->storeAs('report-templates/'.($programId ?: 'university'), $storedName, 'local');
        if (! $path) {
            abort(500, 'Template upload failed. Please try again.');
        }

        try {
            $inspection = $this->templates->inspect(Storage::disk('local')->path($path), $reportType);
            if ($inspection['fatal']) {
                Storage::disk('local')->delete($path);
                throw ValidationException::withMessages(['file' => $inspection['errors']]);
            }
            if ($request->boolean('activate') && ! $inspection['is_valid']) {
                Storage::disk('local')->delete($path);
                throw ValidationException::withMessages(['file' => $inspection['errors']]);
            }
            $template = ReportTemplate::create([
                'program_id' => $programId,
                'report_type' => $reportType,
                'name' => $validated['name'],
                'original_filename' => $originalName,
                'file_path' => $path,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_size' => $upload->getSize(),
                'checksum' => $checksum,
                'version' => $validated['version'] ?? null,
                'period_scope' => ReportTemplate::SCOPE_ALL_PERIODS,
                'academic_period_id' => null,
                'description' => $validated['description'] ?? null,
                'validation_status' => $inspection['is_valid'] ? 'valid' : 'invalid',
                'validation_result' => $inspection,
                'is_active' => false,
                'uploaded_by' => $request->user()->id,
                'validated_at' => now(),
            ]);
            $replaced = $request->boolean('activate') ? $this->activateRecord($template) : 0;
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return response()->json([
            'message' => $request->boolean('activate')
                ? ($replaced ? 'Official template uploaded. The previous template was replaced.' : 'Official template uploaded and is now active for all reports.')
                : ($inspection['is_valid']
                    ? 'Template uploaded and validated successfully. Preview and activate it before use.'
                    : 'Template uploaded, but validation found issues that must be corrected before activation.'),
            'template' => $this->resource($template->fresh(['program:id,name,code', 'academicPeriod:id,academic_year,semester', 'uploader:id'])),
        ], 201);
    }

    public function validateTemplate(Request $request, ReportTemplate $reportTemplate)
    {
        $this->authorizeManage($request, $reportTemplate);
        $this->ensureFileExists($reportTemplate);
        $inspection = $this->templates->inspect(Storage::disk('local')->path($reportTemplate->file_path), $reportTemplate->report_type);
        $reportTemplate->update([
            'validation_status' => $inspection['is_valid'] ? 'valid' : 'invalid',
            'validation_result' => $inspection,
            'validated_at' => now(),
        ]);

        return response()->json([
            'message' => $inspection['is_valid'] ? 'Template validation passed.' : 'Template validation failed.',
            'validation' => $inspection,
            'template' => $this->resource($reportTemplate->fresh(['program:id,name,code', 'academicPeriod:id,academic_year,semester', 'uploader:id'])),
        ], $inspection['is_valid'] ? 200 : 422);
    }

    public function preview(Request $request, ReportTemplate $reportTemplate)
    {
        $this->authorizeView($request, $reportTemplate);
        $this->ensureFileExists($reportTemplate);
        $dataset = $this->templates->sampleDataset($reportTemplate->report_type);
        $bindingSet = $this->datasets->bindings($dataset, $reportTemplate->academic_year, $reportTemplate->semester);

        return response()->json([
            'template' => $this->resource($reportTemplate->loadMissing(['program:id,name,code', 'academicPeriod:id,academic_year,semester', 'uploader:id'])),
            'html' => $this->templates->renderHtml(
                Storage::disk('local')->path($reportTemplate->file_path),
                $bindingSet['values'], $bindingSet['rows'], $bindingSet['headers'], $bindingSet['summary'],
            ),
            'sample_only' => true,
            'sample_data' => $dataset,
        ]);
    }

    public function activate(Request $request, ReportTemplate $reportTemplate)
    {
        $this->authorizeManage($request, $reportTemplate);
        $this->ensureFileExists($reportTemplate);
        if ($reportTemplate->validation_status !== 'valid') {
            throw ValidationException::withMessages(['template' => ['Only a successfully validated template can be activated.']]);
        }

        $replaced = $this->activateRecord($reportTemplate);

        return response()->json([
            'message' => $replaced
                ? 'New template activated. The previous template has been marked as inactive.'
                : ReportTemplateService::label($reportTemplate->report_type).' template is now active.',
            'template' => $this->resource($reportTemplate->fresh(['program:id,name,code', 'academicPeriod:id,academic_year,semester', 'uploader:id'])),
        ]);
    }

    public function deactivate(Request $request, ReportTemplate $reportTemplate)
    {
        $this->authorizeManage($request, $reportTemplate);
        $reportTemplate->update(['is_active' => false, 'active_key' => null, 'deactivated_at' => now()]);

        return response()->json(['message' => 'Template deactivated successfully.', 'template' => $this->resource($reportTemplate->fresh(['program:id,name,code', 'academicPeriod:id,academic_year,semester', 'uploader:id']))]);
    }

    public function download(Request $request, ReportTemplate $reportTemplate)
    {
        $this->authorizeView($request, $reportTemplate);
        $this->ensureFileExists($reportTemplate);

        return $this->privateDisk()->download($reportTemplate->file_path, $reportTemplate->original_filename);
    }

    public function destroy(Request $request, ReportTemplate $reportTemplate)
    {
        $this->authorizeManage($request, $reportTemplate);
        if ($reportTemplate->is_active) {
            throw ValidationException::withMessages(['template' => ['Active templates cannot be deleted. Activate a replacement or deactivate this template first.']]);
        }

        if ($reportTemplate->file_path) {
            Storage::disk('local')->delete($reportTemplate->file_path);
        }
        $reportTemplate->update(['file_path' => null]);
        $reportTemplate->delete();

        return response()->json(['message' => 'Inactive template deleted successfully. Historical generated reports were preserved.']);
    }

    public function activePreview(Request $request)
    {
        [$validated, $template] = $this->activeTemplate($request);
        $dataset = $this->datasets->build($request, $validated['report_type']);
        $bindings = $this->datasets->bindings(
            $dataset,
            $validated['academic_year'] ?? $template->academic_year,
            $validated['semester'] ?? $template->semester,
        );

        return response()->json([
            'template' => $this->resource($template->loadMissing(['program:id,name,code', 'academicPeriod:id,academic_year,semester', 'uploader:id'])),
            'html' => $this->templates->renderHtml(
                Storage::disk('local')->path($template->file_path),
                $bindings['values'],
                $bindings['rows'],
                $bindings['headers'],
                $bindings['summary'],
            ),
            'report' => $dataset,
            'sample_only' => false,
        ]);
    }

    public function generatePdf(Request $request)
    {
        [$validated, $template] = $this->activeTemplate($request);
        $dataset = $this->datasets->build($request, $validated['report_type']);
        $bindings = $this->datasets->bindings(
            $dataset,
            $validated['academic_year'] ?? $template->academic_year,
            $validated['semester'] ?? $template->semester,
        );
        $generated = $this->templates->generatePdf($template, $dataset, $bindings, $request->user());

        return response($generated['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$generated['filename'].'"',
            'X-Generated-Report-Id' => (string) $generated['record']->id,
        ]);
    }

    public function downloadGenerated(Request $request, GeneratedReport $generatedReport)
    {
        $user = $request->user();
        if (in_array($user->role, ['coordinator', 'program_head'], true) && (int) $generatedReport->program_id !== (int) $user->program_id) {
            abort(403, 'You cannot access generated reports outside your assigned program.');
        }
        abort_unless(in_array($user->role, ['coordinator', 'program_head', 'vpaa', 'admin'], true), 403);
        abort_unless(Storage::disk('local')->exists($generatedReport->file_path), 404, 'The generated report file is no longer available.');

        return $this->privateDisk()->download($generatedReport->file_path, $generatedReport->filename);
    }

    private function activeTemplate(Request $request): array
    {
        $this->authorizeTemplateRole($request);
        $reportTypes = ReportTemplateService::reportTypesForRole($request->user()->role);
        $validated = $request->validate([
            'report_type' => ['required', Rule::in(array_keys($reportTypes))],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', Rule::in(['First Semester', 'Second Semester', 'Summer'])],
        ]);
        $programId = in_array($request->user()->role, ['coordinator', 'program_head'], true)
            ? (int) $request->user()->program_id
            : ($validated['program_id'] ?? null);
        $periodId = $this->requestedAcademicPeriodId($validated);

        $template = ReportTemplate::query()
            ->where('is_active', true)
            ->where('validation_status', 'valid')
            ->whereIn('report_type', [ReportTemplateService::SHARED_REPORT_TYPE, $validated['report_type']])
            ->where(function ($query) use ($periodId) {
                $query->where(function ($global) {
                    $global->where('period_scope', ReportTemplate::SCOPE_ALL_PERIODS)
                        ->whereNull('academic_period_id');
                });
                if ($periodId) {
                    $query->orWhere(function ($specific) use ($periodId) {
                        $specific->where('period_scope', ReportTemplate::SCOPE_ACADEMIC_PERIOD)
                            ->where('academic_period_id', $periodId);
                    });
                }
            })
            ->where(function ($query) use ($programId) {
                if ($programId) {
                    $query->where('program_id', $programId)->orWhereNull('program_id');
                } else {
                    $query->whereNull('program_id');
                }
            })
            ->orderByRaw('CASE WHEN program_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN report_type = ? THEN 0 ELSE 1 END', [ReportTemplateService::SHARED_REPORT_TYPE])
            ->orderByRaw("CASE WHEN period_scope = 'academic_period' THEN 0 ELSE 1 END")
            ->latest('activated_at')
            ->first();

        if (! $template) {
            $program = $programId ? Program::find($programId)?->code : 'University';
            abort(404, 'No active '.ReportTemplateService::label($validated['report_type'])." template was found for {$program}. Please attach and activate a template before generating this report.");
        }
        $this->authorizeView($request, $template);
        $this->ensureFileExists($template);

        return [$validated, $template];
    }

    private function authorizeTemplateRole(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['coordinator', 'program_head', 'vpaa'], true), 403);
    }

    private function authorizeView(Request $request, ReportTemplate $template): void
    {
        $this->authorizeTemplateRole($request);
        if (in_array($request->user()->role, ['coordinator', 'program_head'], true)
            && $template->program_id !== null
            && (int) $template->program_id !== (int) $request->user()->program_id) {
            abort(403, 'You cannot access templates outside your assigned program.');
        }
        abort_unless(
            $template->report_type === ReportTemplateService::SHARED_REPORT_TYPE
            || array_key_exists($template->report_type, ReportTemplateService::reportTypesForRole($request->user()->role)),
            403,
        );
    }

    private function authorizeManage(Request $request, ?ReportTemplate $template = null): void
    {
        abort_unless(in_array($request->user()->role, ['coordinator', 'program_head', 'vpaa'], true), 403);
        if ($template) {
            $this->authorizeView($request, $template);
            if ($request->user()->role === 'vpaa' && $template->program_id !== null) {
                abort(403, 'VPAA users may only manage university-level report templates.');
            }
        }
    }

    private function ensureFileExists(ReportTemplate $template): void
    {
        abort_unless($template->file_path && Storage::disk('local')->exists($template->file_path), 404, 'The original template file is no longer available.');
    }

    private function availablePrograms(Request $request)
    {
        if (in_array($request->user()->role, ['coordinator', 'program_head'], true)) {
            return Program::query()->whereKey($request->user()->program_id)->get(['id', 'name', 'code']);
        }

        return Program::query()->where('is_active', true)->orderBy('code')->get(['id', 'name', 'code']);
    }

    private function resource(ReportTemplate $template): array
    {
        return [
            'id' => $template->id,
            'program_id' => $template->program_id,
            'program' => $template->program ? ['id' => $template->program->id, 'code' => $template->program->code, 'name' => $template->program->name] : null,
            'report_type' => $template->report_type,
            'report_type_label' => ReportTemplateService::label($template->report_type),
            'name' => $template->name,
            'file_name' => $template->original_filename,
            'file_size' => $template->file_size,
            'version' => $template->version,
            'period_scope' => $template->period_scope,
            'academic_period_id' => $template->academic_period_id,
            'academic_year' => $template->academic_year,
            'semester' => $template->semester,
            'description' => $template->description,
            'validation_status' => $template->validation_status,
            'validation' => $template->validation_result,
            'placeholders' => $template->detected_placeholders ?? [],
            'status' => $template->is_active ? 'Active' : 'Inactive',
            'is_active' => $template->is_active,
            'uploaded_by' => $template->uploader?->name,
            'uploaded_at' => $template->created_at?->toIso8601String(),
            'validated_at' => $template->validated_at?->toIso8601String(),
            'activated_at' => $template->activated_at?->toIso8601String(),
            'deactivated_at' => $template->deactivated_at?->toIso8601String(),
        ];
    }

    private function activateRecord(ReportTemplate $template): int
    {
        return DB::transaction(function () use ($template) {
            $previousQuery = ReportTemplate::query()
                ->where('program_id', $template->program_id)
                ->where('report_type', $template->report_type)
                ->where('period_scope', $template->period_scope)
                ->when(
                    $template->period_scope === ReportTemplate::SCOPE_ACADEMIC_PERIOD,
                    fn ($query) => $query->where('academic_period_id', $template->academic_period_id),
                    fn ($query) => $query->whereNull('academic_period_id'),
                );
            $previous = $previousQuery
                ->where('is_active', true)
                ->where('id', '!=', $template->id)
                ->lockForUpdate()
                ->get();
            ReportTemplate::query()->whereKey($previous->pluck('id'))->update([
                'is_active' => false,
                'active_key' => null,
                'deactivated_at' => now(),
            ]);
            $template->update([
                'is_active' => true,
                'active_key' => $template->activationKey(),
                'activated_at' => now(),
                'deactivated_at' => null,
            ]);

            return $previous->count();
        });
    }

    private function requestedAcademicPeriodId(array $validated): ?int
    {
        if (empty($validated['academic_year']) || empty($validated['semester'])) {
            return null;
        }

        return AcademicPeriod::query()
            ->where('academic_year', AcademicPeriod::normalizeYear($validated['academic_year']))
            ->where('semester', AcademicPeriod::normalizeSemester($validated['semester']))
            ->value('id');
    }
}
