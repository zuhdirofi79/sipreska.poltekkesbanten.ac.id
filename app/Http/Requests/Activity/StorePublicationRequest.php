<?php

namespace App\Http\Requests\Activity;

use App\Enums\PublicationPlatform;
use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');

        return $this->user()?->can('manageDocuments', $activity) ?? false;
    }

    public function rules(): array
    {
        return $this->baseRules();
    }

    protected function baseRules(): array
    {
        return [
            'platform' => ['required', Rule::enum(PublicationPlatform::class)],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'published_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
