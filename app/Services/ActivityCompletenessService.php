<?php

namespace App\Services;

use App\Models\Activity;

class ActivityCompletenessService
{
    private readonly bool $publicationOptional;

    public function __construct()
    {
        $this->publicationOptional = (bool) config('sipreska.publication_optional', true);
    }

    public static function make(): self
    {
        return new self;
    }

    public function evaluate(Activity $activity): array
    {
        $activity->loadMissing([
            'invitationDocument',
            'minutesDocument',
            'documentations',
            'publications',
            'attendanceForm.responses',
        ]);

        $components = [
            'invitation' => $activity->invitationDocument !== null,
            'attendance' => $activity->attendanceForm?->responses()->exists() ?? false,
            'minutes' => $activity->minutesDocument !== null,
            'documentation' => $activity->documentations->isNotEmpty(),
            'publication' => $activity->publications->isNotEmpty(),
        ];

        $required = $this->publicationOptional
            ? ['invitation', 'attendance', 'minutes', 'documentation']
            : array_keys($components);

        $completed = collect($required)->filter(fn ($key) => $components[$key])->count();
        $total = count($required);

        $missing = collect($components)
            ->filter(fn ($done, $key) => in_array($key, $required, true) && ! $done)
            ->keys()
            ->map(fn ($key) => $this->label($key))
            ->values()
            ->all();

        return [
            'components' => $components,
            'completed' => $completed,
            'total' => $total,
            'missing' => $missing,
            'label' => $completed === $total ? "{$completed}/{$total} Lengkap" : "{$completed}/{$total}",
            'publication_optional' => $this->publicationOptional,
            'publication_available' => $components['publication'],
        ];
    }

    private function label(string $key): string
    {
        return match ($key) {
            'invitation' => 'Surat Undangan',
            'attendance' => 'Daftar Hadir',
            'minutes' => 'Notulensi',
            'documentation' => 'Dokumentasi',
            'publication' => 'Publikasi',
            default => $key,
        };
    }
}
