<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationSubgroup extends Model
{
    protected $guarded = ['id'];

    public function criteria()
    {
        return $this->hasMany(EvaluationCriterion::class)->orderBy('sort_order');
    }
}
