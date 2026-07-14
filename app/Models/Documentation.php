<?php

namespace App\Models;

use App\Enums\DocumentationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documentation extends Model
{
    protected $table = 'documentations';

    protected $fillable = [
        'activity_id',
        'title',
        'type',
        'description',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentationType::class,
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
