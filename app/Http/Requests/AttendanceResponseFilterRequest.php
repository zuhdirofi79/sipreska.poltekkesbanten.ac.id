<?php

namespace App\Http\Requests;

use App\Enums\AttendanceWorkUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceResponseFilterRequest extends FormRequest
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
            'work_unit' => ['nullable', 'string', Rule::enum(AttendanceWorkUnit::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc'])],
        ];
    }

    /** @return array{search: ?string, work_unit: ?string, date_from: ?string, date_to: ?string, sort: string} */
    public function filters(): array
    {
        return [
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'work_unit' => $this->filled('work_unit') ? (string) $this->input('work_unit') : null,
            'date_from' => $this->filled('date_from') ? (string) $this->input('date_from') : null,
            'date_to' => $this->filled('date_to') ? (string) $this->input('date_to') : null,
            'sort' => (string) ($this->input('sort') ?: 'oldest'),
        ];
    }
}
