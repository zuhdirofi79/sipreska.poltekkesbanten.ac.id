<?php

namespace App\Http\Requests\Activity;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Activity::class) ?? false;
    }

    public function rules(): array
    {
        return $this->baseRules();
    }

    protected function prepareForValidation(): void
    {
        /** @var User $user */
        $user = $this->user();

        if ($user->isAdminUnit()) {
            $this->merge(['unit_id' => $user->unit_id]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if ($start && $end && $end < $start) {
                $validator->errors()->add('end_time', 'Waktu selesai tidak boleh lebih awal dari waktu mulai.');
            }
        });
    }

    protected function baseRules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'activity_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'unit_id' => $user->isSuperadmin()
                ? ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)]
                : [],
            'pic_name' => ['required', 'string', 'max:255'],
            'pic_contact' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'target_participants' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
