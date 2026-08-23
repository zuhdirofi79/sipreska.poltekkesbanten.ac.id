<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExternalAttendanceParticipant extends Model
{
    protected $fillable = [
        'external_activity_id',
        'full_name',
        'institution',
        'email',
        'email_normalized',
        'signature_disk',
        'signature_path',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ExternalActivity::class, 'external_activity_id');
    }

    public function attendanceDates(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalActivityDate::class,
            'external_attendance_participant_dates',
            'participant_id',
            'external_activity_date_id',
        )->orderBy('event_date');
    }

    public function formattedAttendanceDates(?string $format = 'd M Y'): string
    {
        return $this->attendanceDates
            ->map(fn (ExternalActivityDate $date) => $date->event_date?->translatedFormat($format))
            ->filter()
            ->implode(', ');
    }

    public function referenceCode(): string
    {
        $hash = hash(
            'sha256',
            $this->id.'|'.$this->submitted_at?->timestamp.'|'.config('app.key'),
        );

        return strtoupper(substr($hash, 0, 8));
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'full_name' => $this->full_name,
            'institution' => $this->institution,
            'email' => $this->email,
            'attendance_dates' => $this->attendanceDates
                ->map(fn (ExternalActivityDate $date) => $date->event_date?->format('Y-m-d'))
                ->all(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
