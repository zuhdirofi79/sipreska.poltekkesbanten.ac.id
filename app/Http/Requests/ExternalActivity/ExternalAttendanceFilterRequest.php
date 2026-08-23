<?php

namespace App\Http\Requests\ExternalActivity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExternalAttendanceFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'attendance_date' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc'])],
        ];
    }

    /** @return array{search: ?string, institution: ?string, email: ?string, attendance_date: ?string, sort: string} */
    public function filters(): array
    {
        return [
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'institution' => $this->filled('institution') ? trim((string) $this->input('institution')) : null,
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
            'attendance_date' => $this->filled('attendance_date') ? (string) $this->input('attendance_date') : null,
            'sort' => (string) ($this->input('sort') ?: 'oldest'),
        ];
    }
}
