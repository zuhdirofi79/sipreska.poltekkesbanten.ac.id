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
        $form = AttendanceForm::query()
            ->where('public_token', (string) $this->route('public_token'))
            ->first();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'nip_nik' => ['required', 'string', 'max:64'],
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

    protected function prepareForValidation(): void
    {
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
}
