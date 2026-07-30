<?php

namespace App\Http\Requests\Activity;

use App\Enums\AttendanceWorkUnit;
use App\Models\AttendanceForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $form = $this->resolveAttendanceForm();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'work_unit' => ['required', Rule::enum(AttendanceWorkUnit::class)],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'website' => ['prohibited'],
        ];

        if ($form?->signature_required) {
            $rules['signature_data'] = ['required', 'string'];
        } else {
            $rules['signature_data'] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nip.required' => 'NIP wajib diisi. Jika tidak memiliki NIP, isi dengan 0 atau -.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nip' => $this->normalizeOptionalInput('nip'),
            // NIK is no longer collected on the public form; keep DB column as archive-only.
            'nik' => null,
        ]);

        if ($this->input('work_unit') === AttendanceWorkUnit::InstansiLainnya->value) {
            $this->merge([
                'institution_name' => trim((string) $this->input('institution_name')),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('website')) {
                $validator->errors()->add('name', 'Permintaan tidak valid.');
            }

            if ($this->input('work_unit') === AttendanceWorkUnit::InstansiLainnya->value
                && ! $this->filled('institution_name')) {
                $validator->errors()->add('institution_name', 'Nama instansi wajib diisi.');
            }
        });
    }

    private function normalizeOptionalInput(string $key): ?string
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function resolveAttendanceForm(): ?AttendanceForm
    {
        $shortCode = $this->route('short_code');

        if (is_string($shortCode) && $shortCode !== '') {
            return AttendanceForm::query()
                ->where('short_code', strtoupper($shortCode))
                ->first();
        }

        $publicToken = $this->route('public_token');

        if (is_string($publicToken) && $publicToken !== '') {
            return AttendanceForm::query()
                ->where('public_token', $publicToken)
                ->first();
        }

        return null;
    }
}
