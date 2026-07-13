<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $role = UserRole::tryFrom((string) $this->input('role'));

            if ($role === UserRole::Superadmin && $this->filled('unit_id')) {
                $validator->errors()->add('unit_id', 'Superadmin tidak boleh memiliki unit.');
            }

            if ($role === UserRole::AdminUnit && ! $this->filled('unit_id')) {
                $validator->errors()->add('unit_id', 'Admin unit wajib memilih unit.');
            }
        });
    }
}
