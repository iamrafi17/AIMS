<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\College;
use App\Models\HTE;
use App\Models\MOA;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Colleges
        $colleges = [
            ['name' => 'College of Information and Computing Sciences', 'code' => 'CICS'],
            ['name' => 'College of Engineering', 'code' => 'COE'],
            ['name' => 'College of Business Administration', 'code' => 'CBA'],
            ['name' => 'College of Education', 'code' => 'CED'],
            ['name' => 'College of Arts and Sciences', 'code' => 'CAS'],
        ];

        foreach ($colleges as $college) {
            College::create($college);
        }

        // Seed Programs
        $programs = [
            ['college_id' => 1, 'name' => 'Bachelor of Science in Computer Science', 'code' => 'BSCS'],
            ['college_id' => 1, 'name' => 'Bachelor of Science in Information Technology', 'code' => 'BSIT'],
            ['college_id' => 2, 'name' => 'Bachelor of Science in Civil Engineering', 'code' => 'BSCE'],
            ['college_id' => 2, 'name' => 'Bachelor of Science in Electrical Engineering', 'code' => 'BSEE'],
            ['college_id' => 3, 'name' => 'Bachelor of Science in Business Administration', 'code' => 'BSBA'],
            ['college_id' => 4, 'name' => 'Bachelor of Secondary Education', 'code' => 'BSED'],
            ['college_id' => 5, 'name' => 'Bachelor of Arts in Political Science', 'code' => 'BAPS'],
        ];

        foreach ($programs as $program) {
            Program::create($program);
        }

        // Seed HTEs
        $htes = [
            [
                'name' => 'Tech Solutions Inc.',
                'address' => '123 Tech Street, Santa Cruz, Laguna',
                'contact_person' => 'John Doe',
                'contact_email' => 'john@techsolutions.com',
                'contact_phone' => '09171234567',
                'latitude' => 14.2766,
                'longitude' => 121.4168,
            ],
            [
                'name' => 'Digital Innovations Corp.',
                'address' => '456 Innovation Ave, Santa Cruz, Laguna',
                'contact_person' => 'Jane Smith',
                'contact_email' => 'jane@digitalinnovations.com',
                'contact_phone' => '09181234567',
                'latitude' => 14.2800,
                'longitude' => 121.4200,
            ],
        ];

        foreach ($htes as $hte) {
            HTE::create($hte);
        }

        // Seed Users
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@marsu.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Coordinator User',
                'email' => 'coordinator@marsu.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'coordinator',
            ],
            [
                'name' => 'Program Head User',
                'email' => 'prohead@marsu.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'program_head',
            ],
            [
                'name' => 'VPAA User',
                'email' => 'vpaa@marsu.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'vpaa',
            ],
            [
                'name' => 'Supervisor User',
                'email' => 'supervisor@marsu.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'supervisor',
            ],
            [
                'name' => 'Student User',
                'email' => 'student@marsu.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'student',
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }

        // Create student profile for student user
        $studentUser = User::where('email', 'student@marsu.edu.ph')->first();
        if ($studentUser) {
            Student::create([
                'user_id' => $studentUser->id,
                'student_id' => '2024-0001',
                'first_name' => 'Student',
                'last_name' => 'User',
                'gender' => 'male',
                'birth_date' => '2000-01-01',
                'address' => 'Santa Cruz, Laguna',
                'phone' => '09191234567',
                'college_id' => 1,
                'program_id' => 1,
                'year_level' => 4,
                'section' => 'A',
                'parent_name' => 'Parent User',
                'parent_relationship' => 'Parent',
                'parent_address' => 'Santa Cruz, Laguna',
                'parent_phone' => '09201234567',
                'hte_id' => 1,
                'internship_semester' => 'First Semester',
                'internship_year' => '2026-2027',
                'internship_status' => 'active',
                'registration_status' => 'approved',
            ]);
        }

        MOA::create([
            'hte_id' => 1,
            'college_id' => 1,
            'file_path' => 'seed/moa-tech-solutions.pdf',
            'effective_date' => now()->startOfYear(),
            'expiration_date' => now()->addYears(2)->endOfYear(),
            'status' => 'approved',
            'approved_by' => User::where('email', 'vpaa@marsu.edu.ph')->value('id'),
            'approved_at' => now(),
        ]);

        MOA::create([
            'hte_id' => 2,
            'college_id' => 1,
            'file_path' => 'seed/moa-digital-innovations.pdf',
            'effective_date' => now()->startOfYear(),
            'expiration_date' => now()->addYears(2)->endOfYear(),
            'status' => 'approved',
            'approved_by' => User::where('email', 'vpaa@marsu.edu.ph')->value('id'),
            'approved_at' => now(),
        ]);

        $adminId = User::where('email', 'admin@marsu.edu.ph')->value('id');

        Announcement::create([
            'title' => 'Internship pre-deployment requirements',
            'content' => 'Students are reminded to complete all documentary requirements before reporting to their assigned host training establishment.',
            'author_id' => $adminId,
            'target_audience' => 'students',
            'is_published' => true,
            'published_at' => now()->subDays(2),
        ]);

        Announcement::create([
            'title' => 'AIMS orientation for student interns',
            'content' => 'The campus internship office will conduct an AIMS orientation covering attendance, journals, travel monitoring, and document submission.',
            'author_id' => $adminId,
            'target_audience' => 'all',
            'is_published' => true,
            'published_at' => now()->subDays(5),
        ]);

        Announcement::create([
            'title' => 'New partner HTE opportunities available',
            'content' => 'Qualified students may coordinate with their internship coordinator to learn about newly available placement opportunities.',
            'author_id' => $adminId,
            'target_audience' => 'students',
            'is_published' => true,
            'published_at' => now()->subWeek(),
        ]);
    }
}
