<?php

namespace App\Models;

use App\Support\PersonName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeProfile extends Model
{
    protected $appends = ['full_name'];

    protected $fillable = [
        'user_id', 'first_name', 'middle_name', 'last_name', 'contact_number', 'address',
        'profile_photo_path', 'profile_completed_at',
        'college_id', 'program_id',
    ];

    public static function splitFullName(string $name): array
    {
        return PersonName::split($name);
    }

    public function getFullNameAttribute(): string
    {
        return PersonName::full($this->first_name, $this->middle_name, $this->last_name);
    }

    protected function casts(): array
    {
        return ['profile_completed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public static function syncAcademicForeignKeysFor(int $profileId): void
    {
        if (! Schema::hasColumns('employee_profiles', ['college_id', 'program_id'])) {
            return;
        }

        $program = DB::table('employee_program_assignments')
            ->join('programs', 'programs.id', '=', 'employee_program_assignments.program_id')
            ->where('employee_program_assignments.employee_profile_id', $profileId)
            ->where('employee_program_assignments.is_active', true)
            ->orderByDesc('employee_program_assignments.id')
            ->first(['programs.id', 'programs.college_id']);

        DB::table('employee_profiles')->where('id', $profileId)->update([
            'program_id' => $program?->id,
            'college_id' => $program?->college_id,
        ]);
    }

    public function programAssignments()
    {
        return $this->hasMany(EmployeeProgramAssignment::class);
    }

    public function activeProgramAssignment()
    {
        return $this->hasOne(EmployeeProgramAssignment::class)->where('is_active', true)->latestOfMany();
    }
}
