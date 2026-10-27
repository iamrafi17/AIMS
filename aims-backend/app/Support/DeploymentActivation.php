<?php

namespace App\Support;

use App\Models\StudentInternship;

class DeploymentActivation
{
    public static function activateDue(): int
    {
        $count = 0;

        StudentInternship::query()
            ->where('is_current', true)
            ->where('status', 'pending')
            ->where('deployment_status', 'approved')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', today())
            ->whereHas('currentPlacement')
            ->chunkById(100, function ($internships) use (&$count): void {
                foreach ($internships as $internship) {
                    $internship->update(['status' => 'active']);
                    $count++;
                }
            });

        return $count;
    }
}
