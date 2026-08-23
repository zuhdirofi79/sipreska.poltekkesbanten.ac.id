<?php

namespace App\Models;

use App\Enums\ExternalActivityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class ExternalActivity extends Model
{
    use HasFactory, SoftDeletes;

    public const SHORT_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const SHORT_CODE_LENGTH = 6;

    protected $fillable = [
        'title',
        'status',
        'public_token',
        'short_code',
        'is_open',
        'signature_required',
        'opens_at',
        'closes_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExternalActivityStatus::class,
            'is_open' => 'boolean',
            'signature_required' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExternalActivity $activity): void {
            if ($activity->public_token === null || $activity->public_token === '') {
                $activity->public_token = bin2hex(random_bytes(32));
            }

            if ($activity->short_code === null || $activity->short_code === '') {
                $activity->short_code = static::generateUniqueShortCode();
            }

            if ($activity->signature_required === null) {
                $activity->signature_required = true;
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

        throw new RuntimeException('Unable to generate unique external attendance short code.');
    }

    public function dates(): HasMany
    {
        return $this->hasMany(ExternalActivityDate::class)->orderBy('event_date');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExternalAttendanceParticipant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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

    public function allowedDateStrings(): array
    {
        return $this->dates
            ->map(fn (ExternalActivityDate $date) => $date->event_date?->format('Y-m-d'))
            ->filter()
            ->values()
            ->all();
    }
}
