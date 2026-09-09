<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HTE extends Model
{
    use HasFactory;

    protected $table = 'htes';

    protected $fillable = [
        'college_id',
        'program_id',
        'name',
        'normalized_name',
        'address',
        'contact_person',
        'contact_email',
        'contact_number',
        'latitude',
        'longitude',
        'geofence_radius',
        'geofence_enabled',
        'default_am_start',
        'default_am_end',
        'default_pm_start',
        'default_pm_end',
        'work_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'geofence_radius' => 'integer',
            'geofence_enabled' => 'boolean',
            'work_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (HTE $hte): void {
            if ($hte->isDirty('name') || blank($hte->normalized_name)) {
                $hte->normalized_name = Str::lower(preg_replace('/\s+/', ' ', trim((string) $hte->name)));
            }
        });

        static::created(function (HTE $hte): void {
            if (! $hte->program_id) {
                return;
            }

            DB::table('hte_programs')->insertOrIgnore([
                'hte_id' => $hte->id,
                'program_id' => $hte->program_id,
                'college_id' => $hte->college_id,
                'linked_by' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'hte_id');
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'hte_programs', 'hte_id', 'program_id')
            ->withPivot(['college_id', 'linked_by', 'is_active'])
            ->withTimestamps();
    }

    public function departments()
    {
        return $this->hasMany(HteDepartment::class, 'hte_id');
    }

    public function colleges()
    {
        return $this->belongsToMany(College::class, 'moas', 'hte_id', 'college_id');
    }

    public function moas()
    {
        return $this->hasMany(MOA::class, 'hte_id');
    }

    public function batchSchedules()
    {
        return $this->hasMany(HteBatchSchedule::class, 'hte_id');
    }
}
