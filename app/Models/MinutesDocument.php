<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinutesDocument extends Model
{
    protected $fillable = [
        'activity_id',
        'minutes_date',
        'notulist_name',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'minutes_date' => 'date',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
