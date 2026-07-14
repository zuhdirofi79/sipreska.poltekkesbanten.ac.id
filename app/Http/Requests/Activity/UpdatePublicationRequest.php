<?php

namespace App\Http\Requests\Activity;

use App\Models\Activity;
use App\Models\Publication;

class UpdatePublicationRequest extends StorePublicationRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');
        /** @var Publication $publication */
        $publication = $this->route('publication');

        return ($this->user()?->can('manageDocuments', $activity) ?? false)
            && (int) $publication->activity_id === (int) $activity->id;
    }
}
