<?php

namespace App\Http\Requests\Activity;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;

class StoreMinutesRequest extends FormRequest
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
            'minutes_date' => ['nullable', 'date'],
            'notulist_name' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:pdf,doc,docx'],
        ];
    }
}
