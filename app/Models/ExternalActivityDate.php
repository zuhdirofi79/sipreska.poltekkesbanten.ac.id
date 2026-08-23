<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExternalActivityDate extends Model
{
    protected $fillable = [
        'external_activity_id',
        'event_date',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ExternalActivity::class, 'external_activity_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalAttendanceParticipant::class,
            'external_attendance_participant_dates',
            'external_activity_date_id',
            'participant_id',
        );
    }

    public function hasParticipants(): bool
    {
        return $this->participants()->exists();
    }
}
