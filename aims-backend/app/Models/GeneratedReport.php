<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_template_id', 'program_id', 'report_type', 'file_path',
        'filename', 'format', 'generated_by', 'data_snapshot',
    ];

    protected function casts(): array
    {
        return ['data_snapshot' => 'array'];
    }

    public function template()
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id')->withTrashed();
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
