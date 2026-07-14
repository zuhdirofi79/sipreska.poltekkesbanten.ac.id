<?php

namespace App\Http\Requests\Activity;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceResponse;
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
            'nip_nik' => ['required', 'string', 'max:64'],
            'position' => ['nullable', 'string', 'max:255'],
            'work_unit' => ['required', Rule::enum(AttendanceWorkUnit::class)],
            'institution_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
