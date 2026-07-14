<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    public function __construct(
        private readonly ActivityCompletenessService $completenessService,
    ) {}

    public function dashboardStats(User $user): array
    {
        $baseQuery = Activity::query()->forUser($user);

        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $statusCounts->sum();

        return [
            'total' => $total,
            'terdaftar' => (int) ($statusCounts[ActivityStatus::Terdaftar->value] ?? 0),
            'selesai' => (int) ($statusCounts[ActivityStatus::Selesai->value] ?? 0),
            'batal' => (int) ($statusCounts[ActivityStatus::Batal->value] ?? 0),
            'this_month' => (clone $baseQuery)
                ->whereYear('activity_date', now()->year)
                ->whereMonth('activity_date', now()->month)
                ->count(),
            'upcoming' => (clone $baseQuery)
                ->where('activity_date', '>=', now()->toDateString())
                ->where('status', ActivityStatus::Terdaftar)
                ->count(),
        ];
    }

    public function chartData(User $user, int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $baseQuery = Activity::query()->forUser($user);

        $monthly = (clone $baseQuery)
            ->where('activity_date', '>=', $start->toDateString())
            ->get(['activity_date'])
            ->groupBy(fn (Activity $activity) => $activity->activity_date?->format('Y-m') ?? '')
            ->map(fn ($group) => $group->count());

        $labels = [];
        $values = [];
        $cursor = $start->copy();

        while ($cursor->lte(now()->endOfMonth())) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->locale('id')->translatedFormat('M Y');
            $values[] = (int) ($monthly[$key] ?? 0);
            $cursor->addMonth();
        }

        $byCategory = (clone $baseQuery)
            ->join('categories', 'categories.id', '=', 'activities.category_id')
            ->select('categories.name', DB::raw('count(*) as total'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->name,
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();

        $statusDistribution = collect(ActivityStatus::cases())
            ->map(function (ActivityStatus $status) use ($baseQuery) {
                return [
                    'label' => $status->label(),
                    'value' => (clone $baseQuery)->where('status', $status)->count(),
                    'color' => $status->color(),
                ];
            })
            ->values()
            ->all();

        return [
            'monthly' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'by_category' => $byCategory,
            'by_status' => $statusDistribution,
        ];
    }

    public function chartByUnit(User $user): array
    {
        if (! $user->isSuperadmin()) {
            return [];
        }

        return Activity::query()
            ->join('units', 'units.id', '=', 'activities.unit_id')
            ->select('units.short_name as label', DB::raw('count(*) as total'))
            ->groupBy('units.id', 'units.short_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    public function incompleteDocuments(User $user, int $limit = 10): Collection
    {
        return Activity::query()
            ->forUser($user)
            ->with(['category', 'unit'])
            ->latest('activity_date')
            ->limit(50)
            ->get()
            ->map(function (Activity $activity) {
                $evaluation = $this->completenessService->evaluate($activity);

                return [
                    'activity' => $activity,
                    'evaluation' => $evaluation,
                ];
            })
            ->filter(fn (array $row) => $row['evaluation']['completed'] < $row['evaluation']['total'])
            ->take($limit)
            ->values();
    }
}
