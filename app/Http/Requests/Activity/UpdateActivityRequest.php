<?php

namespace App\Http\Requests\Activity;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends StoreActivityRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');

        return $this->user()?->can('update', $activity) ?? false;
    }

    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'status' => ['required', Rule::enum(ActivityStatus::class)],
        ]);
    }
}
