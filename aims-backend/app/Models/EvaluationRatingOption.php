<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationRatingOption extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['numeric_value' => 'decimal:3'];
    }
}
