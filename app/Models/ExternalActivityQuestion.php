<?php

namespace App\Models;

use App\Enums\ExternalActivityQuestionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalActivityQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'external_activity_id',
        'label',
        'type',
        'is_required',
        'is_active',
        'sort_order',
        'options',
        'default_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExternalActivityQuestionType::class,
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'array',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ExternalActivity::class, 'external_activity_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExternalAttendanceAnswer::class, 'question_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function allowedOptions(): array
    {
        if (! is_array($this->options)) {
            return [];
        }

        return collect($this->options)
            ->map(fn (mixed $option) => trim((string) $option))
            ->filter()
            ->values()
            ->all();
    }

    public function hasAnswers(): bool
    {
        return $this->answers()->exists();
    }

    public function toBuilderArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type?->value ?? ExternalActivityQuestionType::Text->value,
            'is_required' => $this->is_required ? '1' : '0',
            'is_active' => $this->is_active ? '1' : '0',
            'options_text' => implode("\n", $this->allowedOptions()),
            'default_key' => $this->default_key,
        ];
    }
}
