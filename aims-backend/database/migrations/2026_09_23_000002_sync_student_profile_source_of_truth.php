<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $roles = [
            'student' => 'Student',
            'coordinator' => 'Coordinator',
            'program_head' => 'Program Head',
            'vpaa' => 'VPAA',
            'admin' => 'Administrator',
            'supervisor' => 'HTE Supervisor',
        ];

        foreach ($roles as $code => $name) {
            DB::table('roles')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('role_id')->nullable()->after('user_id')->constrained('roles')->restrictOnDelete();
            });
        }

        DB::table('users')->orderBy('id')->get(['id', 'role'])->each(function (object $user): void {
            $roleId = DB::table('roles')->where('code', $user->role)->value('id');
            DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
        });

        if (! Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('full_name');
                $table->string('contact_number', 20)->nullable();
                $table->text('address')->nullable();
                $table->string('profile_photo_path')->nullable();
                $table->timestamp('profile_completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_program_assignments')) {
            Schema::create('employee_program_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
                $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['employee_profile_id', 'program_id'], 'employee_program_assignment_unique');
            });
        }

        DB::table('users')
            ->whereIn('role', ['admin', 'coordinator', 'program_head', 'vpaa'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                DB::table('employee_profiles')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'full_name' => $user->name,
                        'contact_number' => $user->contact_number,
                        'address' => $user->address,
                        'profile_photo_path' => $user->avatar,
                        'profile_completed_at' => $user->profile_completed_at
                            ?? (! $user->requires_profile_setup ? ($user->created_at ?? now()) : null),
                        'created_at' => $user->created_at ?? now(),
                        'updated_at' => $user->updated_at ?? now(),
                    ],
                );

                if (in_array($user->role, ['coordinator', 'program_head'], true) && $user->program_id) {
                    $profileId = DB::table('employee_profiles')->where('user_id', $user->id)->value('id');
                    DB::table('employee_program_assignments')->updateOrInsert(
                        ['employee_profile_id' => $profileId, 'program_id' => $user->program_id],
                        ['is_active' => $user->is_active, 'updated_at' => now(), 'created_at' => $user->created_at ?? now()],
                    );
                }
            });

        if (! Schema::hasColumn('students', 'profile_photo_path')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->string('profile_photo_path')->nullable()->after('contact_number');
                $table->timestamp('profile_completed_at')->nullable()->after('profile_photo_path');
            });
        }

        DB::table('students')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('students.id')
            ->get([
                'students.id',
                'students.created_at AS student_created_at',
                'students.updated_at AS student_updated_at',
                'users.avatar',
                'users.profile_completed_at AS user_profile_completed_at',
            ])
            ->each(function (object $student): void {
                DB::table('students')->where('id', $student->id)->update([
                    'profile_photo_path' => $student->avatar,
                    'profile_completed_at' => $student->user_profile_completed_at
                        ?? $student->student_updated_at
                        ?? $student->student_created_at,
                ]);
            });

        if (! Schema::hasTable('supervisor_profiles')) {
            Schema::create('supervisor_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $table->string('full_name');
                $table->string('contact_number', 20)->nullable();
                $table->text('address')->nullable();
                $table->string('profile_photo_path')->nullable();
                $table->timestamp('profile_completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hte_supervisor_assignments')) {
            Schema::create('hte_supervisor_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('supervisor_profile_id')->constrained('supervisor_profiles')->cascadeOnDelete();
                $table->foreignId('hte_department_id')->constrained('hte_departments')->restrictOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['supervisor_profile_id', 'hte_department_id'], 'hte_supervisor_department_unique');
            });
        }

        if (Schema::hasTable('supervisors')) {
            DB::table('supervisors')
                ->leftJoin('users', 'users.id', '=', 'supervisors.user_id')
                ->orderBy('supervisors.id')
                ->get([
                    'supervisors.*',
                    'users.contact_number',
                    'users.address',
                    'users.avatar',
                    'users.profile_completed_at',
                ])
                ->each(function (object $supervisor): void {
                    DB::table('supervisor_profiles')->updateOrInsert(
                        ['id' => $supervisor->id],
                        [
                            'user_id' => $supervisor->user_id,
                            'full_name' => $supervisor->full_name,
                            'contact_number' => $supervisor->contact_number,
                            'address' => $supervisor->address,
                            'profile_photo_path' => $supervisor->avatar,
                            'profile_completed_at' => $supervisor->profile_completed_at,
                            'created_at' => $supervisor->created_at ?? now(),
                            'updated_at' => $supervisor->updated_at ?? now(),
                        ],
                    );
                    DB::table('hte_supervisor_assignments')->updateOrInsert(
                        ['supervisor_profile_id' => $supervisor->id, 'hte_department_id' => $supervisor->hte_department_id],
                        ['is_active' => true, 'created_at' => $supervisor->created_at ?? now(), 'updated_at' => now()],
                    );
                });
        }

        // The student record foreign key needs an index with student_record_id
        // as its first column before the old unique index can be removed.
        if (! $this->hasIndex('ojt_enrollments', 'ojt_enrollment_school_section_unique')) {
            Schema::table('ojt_enrollments', function (Blueprint $table): void {
                $table->unique(['school_id', 'academic_section_id'], 'ojt_enrollment_school_section_unique');
            });
        }
        if (! $this->hasIndex('ojt_enrollments', 'ojt_enrollment_student_section_unique')) {
            Schema::table('ojt_enrollments', function (Blueprint $table): void {
                $table->unique(['student_record_id', 'academic_section_id'], 'ojt_enrollment_student_section_unique');
            });
        }
        if ($this->hasIndex('ojt_enrollments', 'ojt_enrollments_school_id_unique')) {
            Schema::table('ojt_enrollments', function (Blueprint $table): void {
                $table->dropUnique('ojt_enrollments_school_id_unique');
            });
        }
        if ($this->hasIndex('ojt_enrollments', 'ojt_enrollments_student_record_id_unique')) {
            Schema::table('ojt_enrollments', function (Blueprint $table): void {
                $table->dropUnique('ojt_enrollments_student_record_id_unique');
            });
        }
        DB::table('students')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('users.role', 'student')
            ->select([
                'students.id',
                'students.user_id',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'students.contact_number',
                'students.address',
            ])
            ->orderBy('students.id')
            ->chunk(100, function ($students): void {
                foreach ($students as $student) {
                    $name = collect([
                        $student->first_name,
                        $student->middle_name,
                        $student->last_name,
                    ])->filter(fn ($part) => filled($part))
                        ->map(fn ($part) => trim((string) $part))
                        ->implode(' ');

                    DB::table('users')->where('id', $student->user_id)->update([
                        'name' => $name,
                        'contact_number' => $student->contact_number,
                        'address' => $student->address,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('hte_supervisor_assignments');
        Schema::dropIfExists('supervisor_profiles');
        Schema::dropIfExists('employee_program_assignments');
        Schema::dropIfExists('employee_profiles');

        if (Schema::hasColumn('students', 'profile_photo_path')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropColumn(['profile_photo_path', 'profile_completed_at']);
            });
        }

        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('role_id');
            });
        }

        Schema::dropIfExists('roles');
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $definition): bool => ($definition['name'] ?? null) === $index,
        );
    }
};
