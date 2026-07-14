<?php

namespace App\Http\Requests\Activity;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceResponse;
use App\Rules\ValidIdentityNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');
        /** @var AttendanceResponse $response */
        $response = $this->route('response');

        return ($this->user()?->can('manageAttendance', $activity) ?? false)
            && (int) $response->attendance_form_id === (int) $activity->attendanceForm?->id;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:32', new ValidIdentityNumber('Format NIK tidak valid.')],
            'nip' => ['nullable', 'string', 'max:32', new ValidIdentityNumber('Format NIP tidak valid.')],
            'position' => ['nullable', 'string', 'max:255'],
            'work_unit' => ['required', Rule::enum(AttendanceWorkUnit::class)],
            'institution_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => $this->normalizeOptionalInput('nik'),
            'nip' => $this->normalizeOptionalInput('nip'),
        ]);
    }

    private function normalizeOptionalInput(string $key): ?string
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
