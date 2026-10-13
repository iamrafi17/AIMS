<?php

namespace Tests\Unit;

use App\Support\RequirementOverview;
use PHPUnit\Framework\TestCase;

class RequirementOverviewTest extends TestCase
{
    public function test_status_priority_and_progress_use_existing_requirements(): void
    {
        $requirement = fn (?string $file, string $status) => (object) [
            'file_path' => $file,
            'status' => $status,
            'program_requirement_id' => null,
            'definition' => null,
        ];

        $overview = RequirementOverview::forStudent(collect([
            $requirement('approved.pdf', 'approved'),
            $requirement(null, 'pending'),
            $requirement('rejected.pdf', 'rejected'),
            $requirement('new.pdf', 'pending'),
        ]));
        $this->assertSame('for_review', $overview['status']);
        $this->assertSame(25.0, $overview['percent']);
        $this->assertSame(1, $overview['missing']);

        $this->assertSame('needs_resubmission', RequirementOverview::forStudent(collect([
            $requirement('rejected.pdf', 'rejected'), $requirement(null, 'pending'),
        ]))['status']);
        $this->assertSame('completed', RequirementOverview::forStudent(collect([
            $requirement('approved.pdf', 'approved'),
        ]))['status']);
    }
}
