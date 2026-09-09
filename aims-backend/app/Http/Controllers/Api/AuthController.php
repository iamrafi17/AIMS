<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\HTE;
use App\Models\InternshipRequirement;
use App\Models\OjtEnrollment;
use App\Models\SecurityQuestion;
use App\Models\Student;
use App\Models\User;
use App\Models\UserSecurityQuestion;
use App\Support\UserId;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string|max:255',
            'device_name' => 'required|string|max:100',
        ]);

        $identifier = trim($request->login);

        $user = User::query()
            ->with(['student', 'supervisorProfile.department.hte'])
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('user_id', strtoupper($identifier))
                    ->orWhereHas('student', function ($studentQuery) use ($identifier) {
                        $studentQuery->where('student_id', $identifier);
                    });
            })
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Your account has been deactivated.'],
            ]);
        }

        if ($user->role === 'student' && $user->student?->registration_status !== 'approved') {
            $message = $user->student?->registration_status === 'rejected'
                ? 'Your registration was not approved. Please contact your internship coordinator.'
                : 'Your registration is still awaiting coordinator approval.';

            throw ValidationException::withMessages(['login' => [$message]]);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken($request->device_name)->plainTextToken;
        ActivityLog::record($user, 'Authentication', 'Logged in.');

        $student = $user->student;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_id' => $user->user_id,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
                'role' => $user->role,
                'college' => $user->college,
                'program' => $user->program,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
                'requires_password_change' => (bool) $user->requires_password_change,
                'requires_security_setup' => $user->requiresStaffSecuritySetup(),
                'requires_profile_setup' => (bool) $user->requires_profile_setup,
                'profile_completed_at' => $user->profile_completed_at,
                'onboarding_step' => $user->onboardingStep(),
                'supervisor_assignment' => $this->supervisorAssignmentPayload($user),
            ],
            'student' => $student ? [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'college' => $student->college,
                'program' => $student->program,
                'year_level' => $student->year_level,
                'section' => $student->section,
                'hte' => $student->hte,
                'internship_status' => $student->internship_status,
            ] : null,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|string|unique:students,student_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female',
            'birth_date' => 'required|date',
            'address' => 'required|string|max:1000',
            'contact_number' => ['required', 'string', 'digits:11'],
            'email' => 'required|email|unique:users,email',
            'college_id' => 'nullable|exists:colleges,id',
            'program_id' => 'nullable|exists:programs,id',
            'year_level' => 'required|integer|between:4,5',
            'section' => 'required|string|max:50',
            'parent_name' => 'required|string|max:255',
            'parent_relationship' => 'required|string|max:50',
            'parent_address' => 'required|string|max:1000',
            'parent_contact_number' => ['required', 'string', 'digits:11'],
            'hte_id' => 'nullable|exists:htes,id',
            'internship_semester' => 'required|string|max:30',
            'internship_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'agree_terms' => 'accepted',
            'agree_privacy' => 'accepted',
            'password' => 'required|string|min:8|max:255|confirmed',
            'security_questions' => ['required', 'array', 'size:3'],
            'security_questions.*.security_question_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('security_questions', 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
            'security_questions.*.answer' => ['required', 'string', 'max:255'],
        ]);

        [$user, $student] = DB::transaction(function () use ($request, $validated) {
            $enrollment = OjtEnrollment::where('school_id', $request->student_id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'student_id' => ['Your School No. was not found in the official student list.'],
                ]);
            }

            if ($enrollment->registered_at || $enrollment->student_record_id) {
                throw ValidationException::withMessages([
                    'student_id' => ['An account has already been registered for this School ID.'],
                ]);
            }

            if (! $enrollment->college_id || ! $enrollment->program_id) {
                throw ValidationException::withMessages([
                    'student_id' => ['Your official OJT enrollment has no academic program assignment. Contact your coordinator.'],
                ]);
            }

            if ($request->hte_id && ! HTE::whereKey($request->hte_id)
                ->whereHas('programs', fn ($query) => $query
                    ->whereKey($enrollment->program_id)
                    ->where('hte_programs.is_active', true))
                ->exists()) {
                throw ValidationException::withMessages([
                    'hte_id' => ['The selected HTE is not available for your enrolled program.'],
                ]);
            }

            [$firstName, $lastName] = $this->splitEnrollmentName($enrollment->full_name);

            $user = User::create([
                'name' => $enrollment->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'student',
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'student_id' => $request->student_id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_name' => $request->middle_name,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'contact_number' => $request->contact_number,
                'college_id' => $enrollment->college_id,
                'program_id' => $enrollment->program_id,
                'academic_section_id' => $enrollment->academic_section_id,
                'year_level' => $enrollment->academicSection?->year_level ?? $request->year_level,
                'section' => $enrollment->section,
                'required_ojt_hours' => $enrollment->college?->required_ojt_hours ?? 486,
                'parent_name' => $request->parent_name,
                'parent_relationship' => $request->parent_relationship,
                'parent_address' => $request->parent_address,
                'parent_contact_number' => $request->parent_contact_number,
                'hte_id' => $request->hte_id,
                'internship_semester' => $request->internship_semester,
                'internship_year' => $request->internship_year,
            ]);

            foreach ($validated['security_questions'] as $index => $securityQuestion) {
                UserSecurityQuestion::create([
                    'user_id' => $user->id,
                    'security_question_id' => $securityQuestion['security_question_id'],
                    'position' => $index + 1,
                    'answer_hash' => Hash::make(
                        UserSecurityQuestion::normalizeAnswer($securityQuestion['answer']),
                    ),
                ]);
            }

            InternshipRequirement::ensureForStudent($student->id);

            $enrollment->update([
                'student_record_id' => $student->id,
                'registered_at' => now(),
            ]);

            return [$user, $student];
        });

        return response()->json([
            'message' => 'Registration successful. Please wait for approval.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
            ],
        ], 201);
    }

    public function enrollment(string $schoolId)
    {
        $enrollment = OjtEnrollment::with([
            'college:id,name,code,required_ojt_hours',
            'program:id,college_id,name,code',
            'academicSection:id,program_id,name,year_level,academic_year',
        ])
            ->where('school_id', trim($schoolId))
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Your School No. was not found in the official student list.',
            ], 404);
        }

        if ($enrollment->registered_at || $enrollment->student_record_id) {
            return response()->json([
                'message' => 'An account has already been registered for this School ID.',
            ], 409);
        }

        [$firstName, $lastName] = $this->splitEnrollmentName($enrollment->full_name);

        return response()->json([
            'student_id' => $enrollment->school_id,
            'full_name' => $enrollment->full_name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'section' => $enrollment->section,
            'academic_section' => $enrollment->academicSection,
            'college' => $enrollment->college,
            'program' => $enrollment->program,
            'required_ojt_hours' => (float) ($enrollment->college?->required_ojt_hours ?? 486),
        ]);
    }

    private function splitEnrollmentName(string $fullName): array
    {
        $name = preg_replace('/\s+/', ' ', trim($fullName));

        if (str_contains($name, ',')) {
            [$lastName, $givenNames] = array_map('trim', explode(',', $name, 2));

            return [
                $givenNames ?: $name,
                Str::title(Str::lower($lastName)),
            ];
        }

        $parts = explode(' ', $name);
        $lastName = count($parts) > 1 ? array_pop($parts) : '';
        $firstName = implode(' ', $parts) ?: $name;

        return [$firstName, $lastName];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('supervisorProfile.department.hte');
        $student = $user->student;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_id' => $user->user_id,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
                'role' => $user->role,
                'college' => $user->college,
                'program' => $user->program,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'requires_password_change' => (bool) $user->requires_password_change,
                'requires_security_setup' => $user->requiresStaffSecuritySetup(),
                'requires_profile_setup' => (bool) $user->requires_profile_setup,
                'profile_completed_at' => $user->profile_completed_at,
                'onboarding_step' => $user->onboardingStep(),
                'supervisor_assignment' => $this->supervisorAssignmentPayload($user),
            ],
            'student' => $student ? [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'gender' => $student->gender,
                'birth_date' => $student->birth_date,
                'address' => $student->address,
                'contact_number' => $student->contact_number,
                'college' => $student->college,
                'program' => $student->program,
                'year_level' => $student->year_level,
                'section' => $student->section,
                'parent_name' => $student->parent_name,
                'parent_address' => $student->parent_address,
                'parent_contact_number' => $student->parent_contact_number,
                'hte' => $student->hte,
                'internship_status' => $student->internship_status,
                'registration_status' => $student->registration_status,
            ] : null,
        ]);
    }

    public function securityQuestions()
    {
        return response()->json([
            'questions' => SecurityQuestion::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'question']),
        ]);
    }

    public function completeStaffPasswordSetup(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user && UserId::isStaffRole($user->role) && $user->requires_password_change,
            403,
            'This account does not require a temporary password change.',
        );

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Your personal password must be different from the temporary password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'requires_password_change' => false,
        ]);

        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        ActivityLog::record($user, 'Authentication', 'Replaced the temporary password during first-login setup.');

        return response()->json([
            'message' => 'Personal password saved. Continue with your security questions.',
            'onboarding_step' => $user->fresh()->onboardingStep(),
        ]);
    }

    public function completeStaffSecurityQuestions(Request $request)
    {
        $user = $request->user();

        abort_if($user?->requires_password_change, 409, 'Change the temporary password first.');
        abort_unless(
            $user && UserId::isStaffRole($user->role) && $user->requires_security_setup,
            403,
            'This account does not require security-question setup.',
        );

        $validated = $request->validate([
            'security_questions' => ['required', 'array', 'size:3'],
            'security_questions.*.security_question_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('security_questions', 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
            'security_questions.*.answer' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $user->securityQuestions()->delete();

            foreach ($validated['security_questions'] as $index => $securityQuestion) {
                UserSecurityQuestion::create([
                    'user_id' => $user->id,
                    'security_question_id' => $securityQuestion['security_question_id'],
                    'position' => $index + 1,
                    'answer_hash' => Hash::make(
                        UserSecurityQuestion::normalizeAnswer($securityQuestion['answer']),
                    ),
                ]);
            }

            $user->update(['requires_security_setup' => false]);
        });

        ActivityLog::record($user, 'Authentication', 'Completed first-login security questions.');

        return response()->json([
            'message' => 'Security questions saved. Complete your personal information.',
            'onboarding_step' => $user->fresh()->onboardingStep(),
        ]);
    }

    public function completeStaffProfile(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user && UserId::isStaffRole($user->role) && $user->requires_profile_setup,
            403,
            'This account does not require first-login profile setup.',
        );

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => ['required', 'string', 'digits:11'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        $user->update([
            ...$validated,
            'requires_profile_setup' => false,
            'profile_completed_at' => now(),
        ]);

        ActivityLog::record($user, 'Profile', 'Completed required personal information during first login.');

        $user = $user->fresh();

        return response()->json([
            'message' => 'Profile saved. Continue with account security setup.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_id' => $user->user_id,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
                'role' => $user->role,
                'college' => $user->college,
                'program' => $user->program,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar_url,
                'requires_password_change' => (bool) $user->requires_password_change,
                'requires_security_setup' => (bool) $user->requires_security_setup,
                'requires_profile_setup' => (bool) $user->requires_profile_setup,
                'profile_completed_at' => $user->profile_completed_at,
                'onboarding_step' => $user->onboardingStep(),
                'supervisor_assignment' => $this->supervisorAssignmentPayload($user),
            ],
            'onboarding_step' => $user->onboardingStep(),
        ]);
    }

    public function completeStaffSecuritySetup(Request $request)
    {
        $user = $request->user();

        abort_unless($user?->requiresStaffSecuritySetup(), 403, 'This account does not require first-login security setup.');

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'security_questions' => ['required', 'array', 'size:3'],
            'security_questions.*.security_question_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('security_questions', 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
            'security_questions.*.answer' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Your personal password must be different from the temporary password.'],
            ]);
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->securityQuestions()->delete();

            foreach ($validated['security_questions'] as $index => $securityQuestion) {
                UserSecurityQuestion::create([
                    'user_id' => $user->id,
                    'security_question_id' => $securityQuestion['security_question_id'],
                    'position' => $index + 1,
                    'answer_hash' => Hash::make(
                        UserSecurityQuestion::normalizeAnswer($securityQuestion['answer']),
                    ),
                ]);
            }

            $user->update([
                'password' => Hash::make($validated['password']),
                'requires_password_change' => false,
                'requires_security_setup' => false,
            ]);

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        ActivityLog::record($user, 'Authentication', 'Completed first-login security setup.');

        return response()->json([
            'message' => 'Security questions and personal password saved successfully.',
            'requires_security_setup' => false,
            'onboarding_step' => $user->fresh()->onboardingStep(),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:100'],
        ]);

        $user = $this->findRecoveryUser($validated['user_id']);
        $challengeToken = Str::random(64);
        $securityQuestions = $user?->securityQuestions()
            ->with('question:id,question')
            ->orderBy('position')
            ->get();

        if ($user && $securityQuestions?->count() === 3) {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => 'challenge:'.Hash::make($challengeToken),
                    'created_at' => now(),
                ],
            );
        } else {
            $securityQuestions = SecurityQuestion::query()
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit(3)
                ->get(['id', 'question']);
        }

        return response()->json([
            'message' => 'If this account is eligible for recovery, answer the security questions.',
            'challenge_token' => $challengeToken,
            'questions' => $securityQuestions->values()->map(fn ($item, int $index) => [
                'id' => $item instanceof UserSecurityQuestion ? $item->security_question_id : $item->id,
                'position' => $item instanceof UserSecurityQuestion ? $item->position : $index + 1,
                'question' => $item instanceof UserSecurityQuestion ? $item->question->question : $item->question,
            ])->values(),
        ]);
    }

    public function verifySecurityAnswers(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:100'],
            'challenge_token' => ['required', 'string'],
            'answers' => ['required', 'array', 'size:3'],
            'answers.*.security_question_id' => ['required', 'integer', 'distinct'],
            'answers.*.answer' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->findRecoveryUser($validated['user_id']);

        if (! $user) {
            return $this->recoveryVerificationFailed();
        }

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        if (! $this->validRecoveryToken($record, 'challenge', $validated['challenge_token'])) {
            return response()->json([
                'message' => 'This password recovery session is invalid or expired.',
            ], 422);
        }

        $configured = $user->securityQuestions()->orderBy('position')->get();
        $submitted = collect($validated['answers'])->keyBy('security_question_id');
        $allCorrect = $configured->count() === 3 && $submitted->count() === 3;

        foreach ($configured as $securityQuestion) {
            $answer = $submitted->get($securityQuestion->security_question_id);
            $correct = $answer && Hash::check(
                UserSecurityQuestion::normalizeAnswer($answer['answer']),
                $securityQuestion->answer_hash,
            );
            $allCorrect = $allCorrect && $correct;
        }

        if (! $allCorrect) {
            return response()->json([
                'message' => 'One or more security answers are incorrect.',
            ], 422);
        }

        $resetToken = Str::random(64);
        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'token' => 'verified:'.Hash::make($resetToken),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Security answers verified.',
            'reset_token' => $resetToken,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:100'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $user = $this->findRecoveryUser($validated['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['This password reset verification is invalid or expired.'],
            ]);
        }

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        if (! $this->validRecoveryToken($record, 'verified', $validated['token'])) {
            throw ValidationException::withMessages([
                'token' => ['This password reset verification is invalid or expired.'],
            ]);
        }

        DB::transaction(function () use ($user, $validated) {
            $user->update(['password' => Hash::make($validated['password'])]);
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        return response()->json(['message' => 'Your password has been successfully reset.']);
    }

    private function findRecoveryUser(string $identifier): ?User
    {
        $identifier = trim($identifier);

        return User::query()
            ->where('user_id', strtoupper($identifier))
            ->orWhereHas('student', fn ($query) => $query->where('student_id', $identifier))
            ->first();
    }

    private function recoveryVerificationFailed()
    {
        return response()->json([
            'message' => 'The recovery answers or session are invalid.',
        ], 422);
    }

    private function validRecoveryToken(?object $record, string $stage, string $plainToken): bool
    {
        if (
            ! $record
            || ! str_starts_with($record->token, $stage.':')
            || now()->diffInMinutes(Carbon::parse($record->created_at)) > 15
        ) {
            return false;
        }

        return Hash::check($plainToken, Str::after($record->token, ':'));
    }

    private function supervisorAssignmentPayload(User $user): ?array
    {
        if (! $user->isSupervisor()) {
            return null;
        }

        $user->loadMissing('supervisorProfile.department.hte');
        $profile = $user->supervisorProfile;

        if (! $profile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'full_name' => $profile->full_name,
            'department' => $profile->department?->only(['id', 'department_name']),
            'hte' => $profile->department?->hte?->only(['id', 'name', 'address']),
        ];
    }
}
