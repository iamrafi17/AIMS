<?php

namespace App\Models;

use App\Support\HteScheduleNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HTE extends Model
{
    use HasFactory;

    private const LEGACY_SCHEDULE_ATTRIBUTES = [
        'default_am_start', 'default_am_end', 'default_pm_start', 'default_pm_end', 'work_days',
    ];

    protected array $scheduleInput = [];

    protected $table = 'htes';

    protected $fillable = [
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

        static::saved(function (HTE $hte): void {
            HteScheduleNormalizer::syncLegacyInput($hte, $hte->scheduleInput);
            $hte->scheduleInput = [];
            $hte->unsetRelation('schedules');
        });
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::LEGACY_SCHEDULE_ATTRIBUTES, true)) {
            $this->scheduleInput[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        if (in_array($key, self::LEGACY_SCHEDULE_ATTRIBUTES, true)) {
            if (array_key_exists($key, $this->scheduleInput)) {
                return $this->scheduleInput[$key];
            }

            return $this->resolveScheduleAttribute($key);
        }

        return parent::getAttribute($key);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach (self::LEGACY_SCHEDULE_ATTRIBUTES as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }

    private function resolveScheduleAttribute(string $key): mixed
    {
        $rows = $this->relationLoaded('schedules') ? $this->schedules : $this->schedules()->get();
        $working = $rows->where('is_working', true);
        $template = $working->first();

        return match ($key) {
            'work_days' => collect(HteScheduleNormalizer::WEEKDAYS)
                ->filter(fn (string $day) => $working->contains('day_of_week', $day))
                ->values()
                ->all(),
            'default_am_start' => $template?->am_start,
            'default_am_end' => $template?->am_end,
            'default_pm_start' => $template?->pm_start,
            'default_pm_end' => $template?->pm_end,
        };
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'hte_programs', 'hte_id', 'program_id')
            ->withPivot(['linked_by', 'is_active'])
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

    public function schedules()
    {
        return $this->hasMany(HteSchedule::class, 'hte_id');
    }

    public function programPlacements()
    {
        return $this->hasManyThrough(
            StudentInternshipPlacement::class,
            HteProgram::class,
            'hte_id',
            'hte_program_id',
            'id',
            'id',
        );
    }
}
