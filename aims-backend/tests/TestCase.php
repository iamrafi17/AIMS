<?php

namespace Tests;

use App\Models\AcademicPeriod;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function academicPeriod(string $year = '2026-2027', string $semester = '2nd Semester'): AcademicPeriod
    {
        return AcademicPeriod::firstOrCreate(
            ['academic_year' => $year, 'semester' => $semester],
            [
                'start_date' => $semester === '1st Semester' ? '2026-08-01' : '2027-01-01',
                'end_date' => $semester === '1st Semester' ? '2026-12-31' : '2027-05-31',
                'status' => 'draft',
            ],
        );
    }
}
