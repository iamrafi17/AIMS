<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
    ];

    public static function minimumPasswordLength(): int
    {
        $value = static::where('key', 'minimum_password_length')->value('value');

        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 8, 'max_range' => 72],
        ]) ?: 8;
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', 'min:'.static::minimumPasswordLength(), 'max:255'];
    }
}
