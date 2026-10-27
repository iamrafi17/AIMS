<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationForm extends Model
{
    protected $guarded = ['id', 'published_slot'];

    protected $hidden = ['published_slot'];

    protected function casts(): array
    {
        return ['allow_no_rating' => 'boolean', 'computation_settings' => 'array', 'published_at' => 'datetime'];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function categories()
    {
        return $this->hasMany(EvaluationCategory::class)->orderBy('sort_order');
    }

    public function criteria()
    {
        return $this->hasMany(EvaluationCriterion::class)->orderBy('sort_order');
    }

    public function ratingOptions()
    {
        return $this->hasMany(EvaluationRatingOption::class)->orderBy('sort_order');
    }

    public function full(): self
    {
        return $this->loadMissing(['program', 'academicPeriod', 'ratingOptions', 'categories.criteria', 'categories.subgroups.criteria']);
    }
}
