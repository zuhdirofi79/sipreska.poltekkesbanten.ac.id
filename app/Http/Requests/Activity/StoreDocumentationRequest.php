<?php

namespace App\Http\Requests\Activity;

use App\Enums\DocumentationType;
use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentationRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DocumentationType::class)],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
