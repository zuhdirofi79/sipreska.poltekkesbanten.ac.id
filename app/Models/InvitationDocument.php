<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationDocument extends Model
{
    protected $fillable = [
        'activity_id',
        'letter_number',
        'letter_date',
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
            'letter_date' => 'date',
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
