<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StatisticsService $statisticsService,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $charts = $this->statisticsService->chartData($user);
        $incomplete = $this->statisticsService->incompleteDocuments($user);
        $stats = $this->statisticsService->dashboardStats($user);
        $stats['incomplete'] = $incomplete->count();

        return view('dashboard.index', [
            'stats' => $stats,
            'chartByMonth' => collect($charts['monthly']['labels'] ?? [])
                ->zip($charts['monthly']['values'] ?? [])
                ->map(fn ($pair) => ['label' => $pair[0], 'total' => $pair[1]])
                ->values()
                ->all(),
            'chartByCategory' => collect($charts['by_category'] ?? [])
                ->map(fn (array $row) => ['label' => $row['label'], 'total' => $row['value']])
                ->all(),
            'chartByUnit' => $this->statisticsService->chartByUnit($user),
            'incompleteActivities' => $incomplete->map(fn (array $row) => [
                'activity' => $row['activity'],
                'completeness' => $row['evaluation'],
            ])->all(),
        ]);
    }
}
