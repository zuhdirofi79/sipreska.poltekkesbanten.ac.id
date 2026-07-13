<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
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
            /** @var User $target */
            $target = $this->route('user');
            $actor = $this->user();
            $role = UserRole::tryFrom((string) $this->input('role'));
            $isActive = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN);

            if ($role === UserRole::Superadmin && $this->filled('unit_id')) {
                $validator->errors()->add('unit_id', 'Superadmin tidak boleh memiliki unit.');
            }

            if ($role === UserRole::AdminUnit && ! $this->filled('unit_id')) {
                $validator->errors()->add('unit_id', 'Admin unit wajib memilih unit.');
            }

            if ($actor !== null && ! $actor->can('demoteOrDeactivate', [$target, $role, $isActive])) {
                $validator->errors()->add('is_active', 'Tidak dapat menonaktifkan atau menurunkan superadmin terakhir.');
            }
        });
    }
}
