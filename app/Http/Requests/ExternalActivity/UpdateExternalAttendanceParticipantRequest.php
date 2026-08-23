<?php

namespace App\Http\Requests\ExternalActivity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExternalAttendanceParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'attendance_dates' => ['required', 'array', 'min:1'],
            'attendance_dates.*' => ['required', 'date'],
        ];
    }
}
