<?php

namespace App\Http\Controllers;

use App\Enums\ReservedSlug;
use App\Models\EventForm;
use Illuminate\Http\RedirectResponse;

class LegacyFormRedirectController extends Controller
{
    public function __invoke(string $slug): RedirectResponse
    {
        if (ReservedSlug::isReserved($slug)) {
            abort(404);
        }

        $exists = EventForm::query()
            ->where('slug', $slug)
            ->exists();

        if (! $exists) {
            abort(404);
        }

        return redirect()->route('public-form.show', ['slug' => $slug], 302);
    }
}
