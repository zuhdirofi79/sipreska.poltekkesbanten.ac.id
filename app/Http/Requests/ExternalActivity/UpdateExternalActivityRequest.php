<?php

namespace App\Http\Requests\ExternalActivity;

use App\Enums\ExternalActivityStatus;
use App\Models\ExternalActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExternalActivity $activity */
        $activity = $this->route('external_activity');

        return $this->user()?->can('update', $activity) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opens_at' => $this->filled('opens_at') ? $this->input('opens_at') : null,
            'closes_at' => $this->filled('closes_at') ? $this->input('closes_at') : null,
            'location_address' => $this->nullableTrimmed('location_address'),
            'online_url' => $this->nullableTrimmed('online_url'),
            'dates' => array_values(array_filter((array) $this->input('dates', []), fn ($date) => filled($date))),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ExternalActivityStatus::class)],
            'location_address' => ['nullable', 'string'],
            'online_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['required', 'date', 'distinct'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'is_open' => ['sometimes', 'boolean'],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        if (! $this->exists($key)) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    public function messages(): array
    {
        return [
            'dates.required' => 'Minimal satu tanggal kegiatan wajib dipilih.',
            'dates.min' => 'Minimal satu tanggal kegiatan wajib dipilih.',
            'dates.*.distinct' => 'Tanggal kegiatan tidak boleh duplikat.',
            'online_url.url' => 'Link kegiatan online harus berupa URL yang valid.',
        ];
    }
}
