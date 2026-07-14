<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ActivityLog::class);

        $logs = ActivityLog::query()
            ->with('actor')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('actor_user_id'), fn ($query) => $query->where('actor_user_id', $request->integer('actor_user_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'actors' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'entityTypes' => ActivityLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type'),
        ]);
    }
}
