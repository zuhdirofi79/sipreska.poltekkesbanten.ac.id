<?php

namespace App\Http\Requests\ExternalActivity;

use App\Enums\ExternalActivityQuestionType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesExternalActivityQuestions
{
    /** @return array<string, mixed> */
    protected function customQuestionRules(): array
    {
        return [
            'use_custom_questions' => ['sometimes', 'boolean'],
            'questions' => ['exclude_unless:use_custom_questions,1', 'sometimes', 'array'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.label' => ['required', 'string', 'max:255'],
            'questions.*.type' => ['required', Rule::enum(ExternalActivityQuestionType::class)],
            'questions.*.is_required' => ['sometimes', 'boolean'],
            'questions.*.is_active' => ['sometimes', 'boolean'],
            'questions.*.options_text' => ['nullable', 'string'],
            'questions.*.default_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function validateQuestionOptions(Validator $validator): void
    {
        if (! $this->boolean('use_custom_questions')) {
            return;
        }

        foreach ($this->input('questions', []) as $index => $question) {
            $type = ExternalActivityQuestionType::tryFrom((string) ($question['type'] ?? ''));

            if ($type === null || ! $type->requiresOptions()) {
                continue;
            }

            $options = collect(preg_split('/\r\n|\r|\n/', (string) ($question['options_text'] ?? '')) ?: [])
                ->map(fn (string $line) => trim($line))
                ->filter();

            if ($options->isEmpty()) {
                $validator->errors()->add(
                    "questions.{$index}.options_text",
                    'Opsi wajib diisi untuk tipe pertanyaan ini. Tulis satu opsi per baris.',
                );
            }
        }
    }
}
