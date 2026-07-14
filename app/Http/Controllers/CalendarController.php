<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $activities = Activity::query()
            ->forUser($request->user())
            ->with(['category', 'unit'])
            ->orderBy('activity_date')
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'title' => $activity->title,
                'date' => $activity->activity_date?->format('Y-m-d'),
                'start' => $activity->start_time ? substr((string) $activity->start_time, 0, 5) : null,
                'end' => $activity->end_time ? substr((string) $activity->end_time, 0, 5) : null,
                'location' => $activity->location,
                'status' => $activity->status->label(),
                'color' => $activity->status->color(),
                'category' => $activity->category?->name,
                'unit' => $activity->unit?->short_name,
                'url' => route('activities.show', $activity),
            ])
            ->values()
            ->all();

        return view('calendar.index', [
            'events' => $activities,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $start = $request->string('start')->toString();
        $end = $request->string('end')->toString();

        $activities = Activity::query()
            ->forUser($request->user())
            ->with(['category', 'unit'])
            ->when($start !== '', fn ($query) => $query->whereDate('activity_date', '>=', $start))
            ->when($end !== '', fn ($query) => $query->whereDate('activity_date', '<=', $end))
            ->orderBy('activity_date')
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'title' => $activity->title,
                'start' => $activity->activity_date?->format('Y-m-d'),
                'url' => route('activities.show', $activity),
                'backgroundColor' => $activity->status->color(),
                'borderColor' => $activity->status->color(),
                'extendedProps' => [
                    'status' => $activity->status->label(),
                    'location' => $activity->location,
                    'category' => $activity->category?->name,
                    'unit' => $activity->unit?->short_name,
                ],
            ]);

        return response()->json($activities);
    }
}
