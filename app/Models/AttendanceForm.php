<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class AttendanceForm extends Model
{
    public const SHORT_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const SHORT_CODE_LENGTH = 6;

    protected $fillable = [
        'activity_id',
        'public_token',
        'short_code',
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

    protected static function booted(): void
    {
        static::creating(function (AttendanceForm $form): void {
            if ($form->short_code === null || $form->short_code === '') {
                $form->short_code = static::generateUniqueShortCode();
            }
        });
    }

    public static function generateUniqueShortCode(int $length = self::SHORT_CODE_LENGTH, int $maxAttempts = 20): string
    {
        $alphabet = self::SHORT_CODE_ALPHABET;
        $alphabetLength = strlen($alphabet);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';

            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, $alphabetLength - 1)];
            }

            if (! static::query()->where('short_code', $code)->exists()) {
                return $code;
            }
        }

        if ($length < 12) {
            return static::generateUniqueShortCode($length + 1, $maxAttempts);
        }

        throw new RuntimeException('Unable to generate unique attendance short code.');
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
