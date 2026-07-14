<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Unit $unit */
        $unit = $this->route('unit');

        return $this->user()?->can('update', $unit) ?? false;
    }

    public function rules(): array
    {
        /** @var Unit $unit */
        $unit = $this->route('unit');

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('units', 'slug')->ignore($unit->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
