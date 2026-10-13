<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->orderBy('id')->get()->each(function (object $user): void {
            $table = match ($user->role) {
                'student' => 'students',
                'supervisor' => 'supervisor_profiles',
                default => 'employee_profiles',
            };
            $profile = DB::table($table)->where('user_id', $user->id)->first();
            if (! $profile) {
                return;
            }

            $changes = [];
            foreach (['contact_number' => 'contact_number', 'address' => 'address', 'avatar' => 'profile_photo_path', 'profile_completed_at' => 'profile_completed_at'] as $source => $target) {
                if ($profile->{$target} === null && $user->{$source} !== null) {
                    $changes[$target] = $user->{$source};
                }
            }
            if ($changes) {
                DB::table($table)->where('id', $profile->id)->update($changes);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['college_id']);
            $table->dropForeign(['program_id']);
            $table->dropIndex('users_role_active_name_index');
            $table->dropIndex('users_role_program_id_index');
            $table->dropColumn([
                'name', 'contact_number', 'address', 'role', 'college_id', 'program_id',
                'avatar', 'requires_profile_setup', 'profile_completed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable();
            $table->string('contact_number', 11)->nullable();
            $table->text('address')->nullable();
            $table->enum('role', ['student', 'coordinator', 'program_head', 'vpaa', 'admin', 'supervisor'])->default('student');
            $table->foreignId('college_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('avatar')->nullable();
            $table->boolean('requires_profile_setup')->default(false);
            $table->timestamp('profile_completed_at')->nullable();
            $table->index(['role', 'is_active', 'name'], 'users_role_active_name_index');
            $table->index(['role', 'program_id'], 'users_role_program_id_index');
        });

        DB::table('users')->orderBy('id')->get()->each(function (object $user): void {
            $role = DB::table('roles')->where('id', $user->role_id)->value('code');
            $table = match ($role) {
                'student' => 'students',
                'supervisor' => 'supervisor_profiles',
                default => 'employee_profiles',
            };
            $profile = DB::table($table)->where('user_id', $user->id)->first();
            DB::table('users')->where('id', $user->id)->update([
                'role' => $role,
                'name' => $profile->full_name ?? null,
                'contact_number' => $profile->contact_number ?? null,
                'address' => $profile->address ?? null,
                'avatar' => $profile->profile_photo_path ?? null,
                'profile_completed_at' => $profile->profile_completed_at ?? null,
            ]);
        });
    }
};
