<?php

namespace App\Http\Requests\Activity;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');

        return $this->user()?->can('manageAttendance', $activity) ?? false;
    }

    public function rules(): array
    {
        return [
            'signature_required' => ['required', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ];
    }
}
