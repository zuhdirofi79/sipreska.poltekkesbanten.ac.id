<?php

namespace App\Models;

use App\Enums\PublicationPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    protected $fillable = [
        'activity_id',
        'platform',
        'title',
        'url',
        'published_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'platform' => PublicationPlatform::class,
            'published_at' => 'date',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
