<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCategory extends Model
{
    protected $guarded = ['id'];

    public function criteria()
    {
        return $this->hasMany(EvaluationCriterion::class)->whereNull('evaluation_subgroup_id')->orderBy('sort_order');
    }

    public function subgroups()
    {
        return $this->hasMany(EvaluationSubgroup::class)->orderBy('sort_order');
    }
}
