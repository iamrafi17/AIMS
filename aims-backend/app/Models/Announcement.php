<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'category',
        'author_id',
        'target_audience',
        'attachment_path',
        'attachment_name',
        'is_published',
        'published_at',
        'scheduled_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function readers()
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at');
    }
}
