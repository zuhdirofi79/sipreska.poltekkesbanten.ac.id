<?php

namespace App\Http\Controllers;

use App\Enums\ExternalActivityStatus;
use App\Models\Activity;
use App\Models\ExternalActivity;
use App\Models\User;
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
            'events' => array_values(array_merge($activities, $this->externalCalendarEvents($request->user()))),
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

        return response()->json(
            $activities->concat($this->externalCalendarJsonEvents($request->user(), $start, $end))->values()
        );
    }

    /**
     * Additive calendar source for external activities. Does not alter internal activity mapping.
     *
     * @return list<array<string, mixed>>
     */
    private function externalCalendarEvents(?User $user): array
    {
        if ($user === null || ! $user->can('viewAny', ExternalActivity::class)) {
            return [];
        }

        $events = [];

        ExternalActivity::query()
            ->with('dates')
            ->orderBy('title')
            ->get()
            ->each(function (ExternalActivity $activity) use (&$events): void {
                foreach ($activity->dates as $date) {
                    $dateKey = $date->event_date?->format('Y-m-d');

                    if ($dateKey === null) {
                        continue;
                    }

                    $events[] = [
                        'id' => 'ext-'.$activity->id.'-'.$dateKey,
                        'title' => $activity->title,
                        'date' => $dateKey,
                        'start' => null,
                        'end' => null,
                        'location' => '',
                        'status' => $activity->status->label(),
                        'statusLabel' => $activity->status->label(),
                        'color' => ExternalActivityStatus::CALENDAR_COLOR,
                        'category' => 'Eksternal',
                        'unit' => 'Eksternal',
                        'url' => route('external-activities.show', $activity),
                        'event_type' => 'external',
                        'time' => '',
                        'dateLabel' => $date->event_date?->translatedFormat('d M Y'),
                    ];
                }
            });

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function externalCalendarJsonEvents(?User $user, string $start, string $end): array
    {
        if ($user === null || ! $user->can('viewAny', ExternalActivity::class)) {
            return [];
        }

        $events = [];

        ExternalActivity::query()
            ->with('dates')
            ->orderBy('title')
            ->get()
            ->each(function (ExternalActivity $activity) use (&$events, $start, $end): void {
                foreach ($activity->dates as $date) {
                    $dateKey = $date->event_date?->format('Y-m-d');

                    if ($dateKey === null) {
                        continue;
                    }

                    if ($start !== '' && $dateKey < $start) {
                        continue;
                    }

                    if ($end !== '' && $dateKey > $end) {
                        continue;
                    }

                    $events[] = [
                        'id' => 'ext-'.$activity->id.'-'.$dateKey,
                        'title' => $activity->title,
                        'start' => $dateKey,
                        'url' => route('external-activities.show', $activity),
                        'backgroundColor' => ExternalActivityStatus::CALENDAR_COLOR,
                        'borderColor' => ExternalActivityStatus::CALENDAR_COLOR,
                        'extendedProps' => [
                            'status' => $activity->status->label(),
                            'location' => '',
                            'category' => 'Eksternal',
                            'unit' => 'Eksternal',
                            'event_type' => 'external',
                        ],
                    ];
                }
            });

        return $events;
    }
}
