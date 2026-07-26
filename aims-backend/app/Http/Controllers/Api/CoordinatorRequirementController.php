<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternshipRequirement;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Support\ProgramAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CoordinatorRequirementController extends Controller
{
    public function index(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        ProgramRequirement::ensureDefaultsForProgram($programId);
        $this->syncStudents($programId);

        return response()->json([
            'program' => $request->user()->program,
            'requirements' => $this->requirements($programId),
        ]);
    }

    public function store(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate($this->rules($programId));
        $nextOrder = (int) ProgramRequirement::where('program_id', $programId)->max('sort_order') + 1;

        $definition = ProgramRequirement::create([
            'program_id' => $programId,
            'key' => 'custom-'.Str::uuid(),
            'name' => trim($validated['name']),
            'instructions' => $validated['instructions'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $nextOrder,
            'created_by' => $request->user()->id,
        ]);

        if ($definition->is_active) {
            $this->syncStudents($programId);
        }

        return response()->json([
            'message' => 'Requirement added to the student upload checklist.',
            'requirement' => $this->requirement($definition),
        ], 201);
    }

    public function update(Request $request, ProgramRequirement $programRequirement)
    {
        $programId = $this->authorizeDefinition($request, $programRequirement);
        $validated = $request->validate($this->rules($programId, $programRequirement));

        DB::transaction(function () use ($programRequirement, $validated): void {
            $programRequirement->update([
                'name' => trim($validated['name']),
                'instructions' => $validated['instructions'] ?? null,
                'is_active' => $validated['is_active'] ?? $programRequirement->is_active,
            ]);

            $programRequirement->requirements()->update([
                'requirement_name' => $programRequirement->name,
            ]);
        });

        if ($programRequirement->is_active) {
            $this->syncStudents($programId);
        }

        return response()->json([
            'message' => $programRequirement->is_active
                ? 'Requirement settings updated.'
                : 'Requirement removed from the active student checklist.',
            'requirement' => $this->requirement($programRequirement->fresh()),
        ]);
    }

    public function destroy(Request $request, ProgramRequirement $programRequirement)
    {
        $this->authorizeDefinition($request, $programRequirement);
        $programRequirement->update(['is_active' => false]);

        return response()->json([
            'message' => 'Requirement archived. Existing submitted files were preserved.',
        ]);
    }

    public function reorder(Request $request)
    {
        $programId = ProgramAccess::programId($request->user());
        $validated = $request->validate([
            'requirement_ids' => ['required', 'array', 'min:1'],
            'requirement_ids.*' => [
                'required',
                'integer',
                Rule::exists('program_requirements', 'id')->where('program_id', $programId),
            ],
        ]);

        DB::transaction(function () use ($validated, $programId): void {
            foreach ($validated['requirement_ids'] as $index => $id) {
                ProgramRequirement::where('program_id', $programId)
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json([
            'message' => 'Requirement order updated.',
            'requirements' => $this->requirements($programId),
        ]);
    }

    private function rules(int $programId, ?ProgramRequirement $definition = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('program_requirements', 'name')
                    ->where('program_id', $programId)
                    ->ignore($definition?->id),
            ],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function authorizeDefinition(Request $request, ProgramRequirement $definition): int
    {
        $programId = ProgramAccess::programId($request->user());
        abort_unless((int) $definition->program_id === $programId, 404);

        return $programId;
    }

    private function syncStudents(int $programId): void
    {
        InternshipRequirement::ensureForStudents(
            Student::where('program_id', $programId)->pluck('id')
        );
    }

    private function requirements(int $programId)
    {
        return ProgramRequirement::query()
            ->where('program_id', $programId)
            ->withCount([
                'requirements',
                'requirements as uploaded_count' => fn ($query) => $query->whereNotNull('file_path'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function requirement(ProgramRequirement $definition): ProgramRequirement
    {
        return $definition->loadCount([
            'requirements',
            'requirements as uploaded_count' => fn ($query) => $query->whereNotNull('file_path'),
        ]);
    }
}
