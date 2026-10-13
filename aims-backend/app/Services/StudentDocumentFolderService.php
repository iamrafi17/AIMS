<?php

namespace App\Services;

use App\Models\ProgramRequirement;
use App\Models\StudentInternship;
use Illuminate\Support\Collection;

class StudentDocumentFolderService
{
    public function checklist(StudentInternship $internship): Collection
    {
        $internship->loadMissing('academicSection');
        $programId = $internship->academicSection?->program_id;
        if (! $programId) {
            return collect();
        }

        $definitions = ProgramRequirement::query()
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($definitions->isEmpty()) {
            return collect();
        }

        $requirements = $internship->requirements()
            ->whereIn('program_requirement_id', $definitions->pluck('id'))
            ->with('reviewer')
            ->get()
            ->keyBy('program_requirement_id');

        return $definitions->map(fn (ProgramRequirement $definition) => [
            'definition' => $definition,
            'requirement' => $requirements->get($definition->id),
        ]);
    }

    public function summary(StudentInternship $internship): array
    {
        $checklist = $this->checklist($internship);
        $approved = $checklist->filter(fn (array $item) => $item['requirement']?->file_path
            && $item['requirement']->status === 'approved'
            && $item['requirement']->reviewer?->role === 'program_head')->count();
        return [
            'total' => $checklist->count(),
            'approved' => $approved,
            'complete' => $checklist->isNotEmpty() && $approved === $checklist->count(),
            'items' => $checklist,
        ];
    }
}
