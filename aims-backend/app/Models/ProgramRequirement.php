<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProgramRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'key',
        'name',
        'instructions',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function requirements()
    {
        return $this->hasMany(InternshipRequirement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function ensureDefaultsForProgram(int $programId): void
    {
        foreach (InternshipRequirement::OFFICIAL_REQUIREMENTS as $index => $name) {
            static::query()->firstOrCreate(
                [
                    'program_id' => $programId,
                    'key' => 'default-'.Str::slug($name),
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
