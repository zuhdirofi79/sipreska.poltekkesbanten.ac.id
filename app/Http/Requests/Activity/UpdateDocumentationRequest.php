<?php

namespace App\Http\Requests\Activity;

use App\Enums\DocumentationType;
use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');

        return $this->user()?->can('manageDocuments', $activity) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DocumentationType::class)],
            'description' => ['nullable', 'string'],
        ];
    }
}
