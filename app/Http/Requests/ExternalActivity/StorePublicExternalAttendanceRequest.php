<?php

namespace App\Http\Requests\ExternalActivity;

use App\Enums\ExternalActivityQuestionType;
use App\Models\ExternalActivity;
use App\Models\ExternalActivityQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicExternalAttendanceRequest extends FormRequest
{
    private ?ExternalActivity $resolvedActivity = null;

    private bool $activityResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'attendance_dates' => ['required', 'array', 'min:1'],
            'attendance_dates.*' => ['required', 'date'],
            'signature_data' => ['required', 'string'],
            'website' => ['prohibited'],
        ];

        $activity = $this->resolveExternalActivity();

        if ($activity === null || ! $activity->use_custom_questions) {
            return $rules;
        }

        $rules['custom_answers'] = ['sometimes', 'array'];

        foreach ($this->activeQuestions($activity) as $question) {
            $key = 'custom_answers.'.$question->id;
            $rules[$key] = $question->type->answerRules($question->is_required);

            if ($question->type === ExternalActivityQuestionType::Checkbox) {
                $rules[$key.'.*'] = ['string', Rule::in($question->allowedOptions())];
            } elseif (in_array($question->type, [
                ExternalActivityQuestionType::Select,
                ExternalActivityQuestionType::Radio,
            ], true)) {
                $rules[$key][] = Rule::in($question->allowedOptions());
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'full_name.required' => 'Full Name wajib diisi.',
            'institution.required' => 'Institution wajib diisi.',
            'email.required' => 'Email Address wajib diisi.',
            'email.email' => 'Format Email Address tidak valid.',
            'attendance_dates.required' => 'Minimal satu tanggal kehadiran wajib dipilih.',
            'attendance_dates.min' => 'Minimal satu tanggal kehadiran wajib dipilih.',
            'signature_data.required' => 'Tanda tangan wajib diisi.',
        ];

        $activity = $this->resolveExternalActivity();

        if ($activity === null || ! $activity->use_custom_questions) {
            return $messages;
        }

        foreach ($this->activeQuestions($activity) as $question) {
            $key = 'custom_answers.'.$question->id;
            $messages[$key.'.required'] = $question->label.' wajib diisi.';
            $messages[$key.'.email'] = 'Format '.$question->label.' tidak valid.';
            $messages[$key.'.integer'] = $question->label.' harus berupa angka.';
            $messages[$key.'.date'] = 'Format '.$question->label.' tidak valid.';
            $messages[$key.'.in'] = 'Jawaban '.$question->label.' tidak valid.';
            $messages[$key.'.*.in'] = 'Jawaban '.$question->label.' tidak valid.';
        }

        return $messages;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('website')) {
                $validator->errors()->add('full_name', 'Permintaan tidak valid.');

                return;
            }

            $activity = $this->resolveExternalActivity();

            if ($activity === null) {
                $validator->errors()->add('full_name', 'Kegiatan tidak ditemukan.');

                return;
            }

            $allowed = $activity->allowedDateStrings();
            $selected = collect($this->input('attendance_dates', []))
                ->map(fn (mixed $date) => trim((string) $date))
                ->filter()
                ->unique()
                ->values();

            foreach ($selected as $date) {
                if (! in_array($date, $allowed, true)) {
                    $validator->errors()->add(
                        'attendance_dates',
                        'Tanggal kehadiran tidak termasuk dalam kegiatan ini.',
                    );

                    break;
                }
            }
        });
    }

    public function resolveExternalActivity(): ?ExternalActivity
    {
        if ($this->activityResolved) {
            return $this->resolvedActivity;
        }

        $this->activityResolved = true;

        $shortCode = $this->route('short_code');

        if (is_string($shortCode) && $shortCode !== '') {
            $this->resolvedActivity = ExternalActivity::query()
                ->with(['dates', 'questions'])
                ->where('short_code', strtoupper($shortCode))
                ->first();

            return $this->resolvedActivity;
        }

        $publicToken = $this->route('public_token');

        if (is_string($publicToken) && $publicToken !== '') {
            $this->resolvedActivity = ExternalActivity::query()
                ->with(['dates', 'questions'])
                ->where('public_token', $publicToken)
                ->first();

            return $this->resolvedActivity;
        }

        return null;
    }

    /** @return \Illuminate\Support\Collection<int, ExternalActivityQuestion> */
    private function activeQuestions(ExternalActivity $activity)
    {
        $questions = $activity->relationLoaded('questions')
            ? $activity->questions
            : $activity->questions()->get();

        return $questions
            ->where('is_active', true)
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
