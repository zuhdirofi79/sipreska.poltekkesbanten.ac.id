<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceForm extends Model
{
    protected $fillable = [
        'activity_id',
        'public_token',
        'is_open',
        'signature_required',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'signature_required' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AttendanceResponse::class);
    }

    public function isAcceptingSubmissions(): bool
    {
        if (! $this->is_open) {
            return false;
        }

        $now = now();

        if ($this->opens_at !== null && $now->lt($this->opens_at)) {
            return false;
        }

        if ($this->closes_at !== null && $now->gt($this->closes_at)) {
            return false;
        }

        return true;
    }
}
