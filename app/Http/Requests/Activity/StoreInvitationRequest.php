<?php

namespace App\Http\Requests\Activity;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');

        return $this->user()?->can('manageDocuments', $activity) ?? false;
    }

    public function rules(): array
    {
        $maxKb = (int) config('sipreska.upload_max_kb', 5120);

        return [
            'letter_number' => ['nullable', 'string', 'max:255'],
            'letter_date' => ['nullable', 'date'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:pdf'],
        ];
    }
}
