<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationResponse extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_no_rating' => 'boolean'];
    }
}
