<?php

namespace App\Http\Requests\ExternalActivity;

use App\Enums\ExternalActivityStatus;
use App\Models\ExternalActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExternalActivity::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opens_at' => $this->filled('opens_at') ? $this->input('opens_at') : null,
            'closes_at' => $this->filled('closes_at') ? $this->input('closes_at') : null,
            'dates' => array_values(array_filter((array) $this->input('dates', []), fn ($date) => filled($date))),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ExternalActivityStatus::class)],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['required', 'date', 'distinct'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'is_open' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'dates.required' => 'Minimal satu tanggal kegiatan wajib dipilih.',
            'dates.min' => 'Minimal satu tanggal kegiatan wajib dipilih.',
            'dates.*.distinct' => 'Tanggal kegiatan tidak boleh duplikat.',
        ];
    }
}
