<?php

namespace App\Services;

use App\Enums\ExternalActivityQuestionType;
use App\Models\ExternalActivity;
use App\Models\ExternalActivityQuestion;
use App\Models\ExternalAttendanceAnswer;
use App\Models\ExternalAttendanceParticipant;

class ExternalActivityQuestionService
{
    /**
     * @return list<array{
     *     default_key: string,
     *     label: string,
     *     type: string,
     *     is_required: string,
     *     is_active: string,
     *     options: ?list<string>
     * }>
     */
    public static function defaultDefinitions(): array
    {
        return [
            [
                'default_key' => 'nama_lengkap',
                'label' => 'Nama Lengkap',
                'type' => ExternalActivityQuestionType::Text->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => null,
            ],
            [
                'default_key' => 'status_peserta',
                'label' => 'Status Peserta',
                'type' => ExternalActivityQuestionType::Select->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => ['Mahasiswa', 'Alumni', 'Dosen', 'Tenaga Kependidikan', 'Umum', 'Lainnya'],
            ],
            [
                'default_key' => 'nomor_hp',
                'label' => 'Nomor HP',
                'type' => ExternalActivityQuestionType::Text->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => null,
            ],
            [
                'default_key' => 'alamat_email',
                'label' => 'Alamat Email',
                'type' => ExternalActivityQuestionType::Email->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => null,
            ],
            [
                'default_key' => 'wilayah_domisili',
                'label' => 'Wilayah Domisili',
                'type' => ExternalActivityQuestionType::Text->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => null,
            ],
            [
                'default_key' => 'asal_institusi_kelulusan',
                'label' => 'Asal Institusi Kelulusan',
                'type' => ExternalActivityQuestionType::Text->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => null,
            ],
            [
                'default_key' => 'tahun_lulus',
                'label' => 'Tahun Lulus',
                'type' => ExternalActivityQuestionType::Number->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => null,
            ],
            [
                'default_key' => 'program_studi',
                'label' => 'Program Studi',
                'type' => ExternalActivityQuestionType::Text->value,
                'is_required' => '1',
                'is_active' => '1',
                'options' => null,
            ],
            [
                'default_key' => 'perusahaan_diharapkan',
                'label' => 'Rumah Sakit/Klinik/Perusahaan yang paling Anda harapkan hadir dalam Job Fair?',
                'type' => ExternalActivityQuestionType::Textarea->value,
                'is_required' => '0',
                'is_active' => '1',
                'options' => null,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function defaultQuestionTemplates(): array
    {
        return collect(self::defaultDefinitions())
            ->map(fn (array $definition) => [
                'id' => null,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'is_required' => $definition['is_required'],
                'is_active' => $definition['is_active'],
                'options_text' => implode("\n", $definition['options'] ?? []),
                'default_key' => $definition['default_key'],
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function defaultKeys(): array
    {
        return collect(self::defaultDefinitions())
            ->pluck('default_key')
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>|null  $incoming
     */
    public function syncOrSeed(ExternalActivity $activity, ?array $incoming): void
    {
        if (is_array($incoming) && $this->hasQuestionLabels($incoming)) {
            $this->sync($activity, $incoming);

            return;
        }

        $this->ensureDefaults($activity);
    }

    public function ensureDefaults(ExternalActivity $activity): void
    {
        if ($activity->questions()->exists()) {
            return;
        }

        $this->seedDefaults($activity);
    }

    public function seedDefaults(ExternalActivity $activity): void
    {
        foreach (self::defaultDefinitions() as $index => $definition) {
            $existing = $activity->questions()
                ->where('default_key', $definition['default_key'])
                ->first();

            if ($existing !== null) {
                continue;
            }

            $activity->questions()->create([
                'label' => $definition['label'],
                'type' => $definition['type'],
                'is_required' => $definition['is_required'] === '1',
                'is_active' => true,
                'sort_order' => $index + 1,
                'options' => $definition['options'],
                'default_key' => $definition['default_key'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $incoming
     */
    public function sync(ExternalActivity $activity, array $incoming): void
    {
        $keptIds = [];
        $sortOrder = 1;

        foreach (array_values($incoming) as $item) {
            $label = trim((string) ($item['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $type = ExternalActivityQuestionType::tryFrom((string) ($item['type'] ?? ''))
                ?? ExternalActivityQuestionType::Text;

            $payload = [
                'label' => $label,
                'type' => $type,
                'is_required' => $this->toBoolean($item['is_required'] ?? false),
                'is_active' => $this->toBoolean($item['is_active'] ?? true),
                'sort_order' => $sortOrder++,
                'options' => $type->requiresOptions()
                    ? $this->parseOptions($item['options_text'] ?? '')
                    : null,
            ];

            $question = $this->resolveOwnedQuestion($activity, $item);

            if ($question !== null) {
                $question->fill($payload)->save();
            } else {
                $defaultKey = $this->sanitizeDefaultKey($item['default_key'] ?? null);
                $payload['default_key'] = $defaultKey;
                $question = $activity->questions()->create($payload);
            }

            $keptIds[] = $question->id;
        }

        $query = $activity->questions();

        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds);
        }

        $query->get()->each(function (ExternalActivityQuestion $question): void {
            $question->is_active = false;
            $question->save();
            $question->delete();
        });
    }

    /**
     * @param  array<int|string, mixed>  $customAnswers
     */
    public function storeAnswers(
        ExternalAttendanceParticipant $participant,
        ExternalActivity $activity,
        array $customAnswers,
    ): void {
        if (! $activity->use_custom_questions) {
            return;
        }

        $questions = $activity->relationLoaded('questions')
            ? $activity->questions->where('is_active', true)->sortBy('sort_order')->values()
            : $activity->questions()->active()->get();

        foreach ($questions as $question) {
            $raw = $customAnswers[$question->id] ?? null;

            ExternalAttendanceAnswer::query()->updateOrCreate(
                [
                    'participant_id' => $participant->id,
                    'question_id' => $question->id,
                ],
                [
                    'value' => $this->normalizeAnswer($question, $raw),
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $incoming
     */
    private function hasQuestionLabels(array $incoming): bool
    {
        foreach ($incoming as $item) {
            if (filled(trim((string) ($item['label'] ?? '')))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveOwnedQuestion(ExternalActivity $activity, array $item): ?ExternalActivityQuestion
    {
        $id = $item['id'] ?? null;

        if (filled($id)) {
            $owned = $activity->questions()->whereKey((int) $id)->first();

            if ($owned !== null) {
                return $owned;
            }
        }

        $defaultKey = $this->sanitizeDefaultKey($item['default_key'] ?? null);

        if ($defaultKey === null) {
            return null;
        }

        return $activity->questions()->where('default_key', $defaultKey)->first();
    }

    private function sanitizeDefaultKey(mixed $key): ?string
    {
        $value = trim((string) $key);

        if ($value === '' || ! in_array($value, self::defaultKeys(), true)) {
            return null;
        }

        return $value;
    }

    private function parseOptions(mixed $text): ?array
    {
        $options = collect(preg_split('/\r\n|\r|\n/', (string) $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $options === [] ? null : $options;
    }

    private function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 1 || $value === '1';
    }

    private function normalizeAnswer(ExternalActivityQuestion $question, mixed $raw): ?string
    {
        if ($question->type === ExternalActivityQuestionType::Checkbox) {
            $selected = collect(is_array($raw) ? $raw : [])
                ->map(fn (mixed $item) => trim((string) $item))
                ->filter()
                ->intersect($question->allowedOptions())
                ->values()
                ->all();

            return $selected === [] ? null : json_encode($selected);
        }

        $value = is_array($raw) ? null : trim((string) $raw);

        return $value === '' ? null : $value;
    }
}
