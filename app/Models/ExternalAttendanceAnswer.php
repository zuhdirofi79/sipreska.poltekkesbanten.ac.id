<?php

namespace App\Models;

use App\Enums\ExternalActivityQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalAttendanceAnswer extends Model
{
    protected $fillable = [
        'participant_id',
        'question_id',
        'value',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(ExternalAttendanceParticipant::class, 'participant_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExternalActivityQuestion::class, 'question_id')->withTrashed();
    }

    public function displayValue(): string
    {
        if ($this->value === null || $this->value === '') {
            return '-';
        }

        if ($this->question?->type === ExternalActivityQuestionType::Checkbox) {
            $decoded = json_decode($this->value, true);

            if (is_array($decoded)) {
                $values = collect($decoded)
                    ->map(fn (mixed $item) => trim((string) $item))
                    ->filter()
                    ->all();

                return $values === [] ? '-' : implode(', ', $values);
            }
        }

        return $this->value;
    }
}
