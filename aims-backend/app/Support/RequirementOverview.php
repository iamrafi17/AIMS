<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class RequirementOverview
{
    public static function forStudent(Collection $requirements): array
    {
        $counts = ['missing' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($requirements as $requirement) {
            if ($requirement->program_requirement_id && ! $requirement->definition?->is_active) {
                continue;
            }
            $status = $requirement->file_path ? $requirement->status : 'missing';
            $counts[array_key_exists($status, $counts) ? $status : 'missing']++;
        }

        $total = array_sum($counts);
        $status = $counts['pending'] ? 'for_review'
            : ($counts['rejected'] ? 'needs_resubmission'
                : ($counts['missing'] || ! $total ? 'incomplete' : 'completed'));

        return [
            ...$counts,
            'total' => $total,
            'percent' => $total ? round($counts['approved'] / $total * 100, 1) : 0,
            'status' => $status,
        ];
    }
}
