<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Requests\Activity\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Unit;
use App\Services\ActivityCompletenessService;
use App\Services\AttendanceService;
use App\Services\AuditLogService;
use App\Services\FileStorageService;
use App\Support\AttendancePublicRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly ActivityCompletenessService $completenessService,
        private readonly AttendanceService $attendanceService,
        private readonly FileStorageService $fileStorage,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $activities = Activity::query()
            ->forUser($request->user())
            ->with(['category', 'unit'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', $search)
                        ->orWhere('location', 'like', $search)
                        ->orWhere('pic_name', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('unit_id') && $request->user()?->isSuperadmin(), fn ($query) => $query->where('unit_id', $request->integer('unit_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('activity_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('activity_date', '<=', $request->string('date_to')))
            ->orderByDesc('activity_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $completenessMap = [];

        foreach ($activities as $activity) {
            $completenessMap[$activity->id] = $this->completenessService->evaluate($activity);
        }

        return view('activities.index', [
            'activities' => $activities,
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'units' => Unit::query()->active()->orderBy('name')->get(),
            'statuses' => ActivityStatus::cases(),
            'completenessMap' => $completenessMap,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Activity::class);

        return view('activities.create', [
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'units' => Unit::query()->active()->orderBy('name')->get(),
            'statuses' => ActivityStatus::cases(),
            'defaultUnitId' => $request->user()?->unit_id,
        ]);
    }

    public function store(StoreActivityRequest $request): RedirectResponse
    {
        $user = $request->user();

        $activity = DB::transaction(function () use ($request, $user) {
            $unitId = $user->isSuperadmin()
                ? $request->integer('unit_id')
                : (int) $user->unit_id;

            $activity = Activity::query()->create([
                'title' => $request->string('title')->toString(),
                'category_id' => $request->integer('category_id'),
                'status' => ActivityStatus::Terdaftar,
                'activity_date' => $request->string('activity_date')->toString(),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'location' => $request->string('location')->toString(),
                'unit_id' => $unitId,
                'pic_name' => $request->string('pic_name')->toString(),
                'pic_contact' => $request->input('pic_contact'),
                'description' => $request->input('description'),
                'objective' => $request->input('objective'),
                'target_participants' => $request->input('target_participants'),
                'notes' => $request->input('notes'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->auditLog->logModelChange(
                'activity_created',
                $activity,
                null,
                $activity->only([
                    'title', 'category_id', 'status', 'activity_date', 'location', 'unit_id', 'pic_name',
                ]),
                $request,
            );

            $this->attendanceService->ensureForm($activity);

            return $activity;
        });

        return redirect()
            ->route('activities.edit', ['activity' => $activity, 'tab' => 'invitation'])
            ->with('success', 'Kegiatan berhasil dibuat. Silakan lengkapi dokumen dan informasi pendukung.');
    }

    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);

        $activity->load([
            'category',
            'unit',
            'creator',
            'invitationDocument',
            'minutesDocument',
            'documentations',
            'publications',
            'attendanceForm',
        ]);

        return view('activities.show', [
            'activity' => $activity,
            'completeness' => $this->completenessService->evaluate($activity),
        ]);
    }

    public function edit(Activity $activity): View
    {
        $this->authorize('update', $activity);

        $activity->load([
            'category',
            'unit',
            'invitationDocument.uploader',
            'minutesDocument.uploader',
            'documentations.uploader',
            'publications',
            'attendanceForm',
        ]);

        $form = $activity->attendanceForm;
        $publicAttendanceUrl = $form?->public_token
            ? AttendancePublicRoutes::showUrl($form->public_token)
            : null;

        return view('activities.edit', [
            'activity' => $activity,
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'units' => Unit::query()->active()->orderBy('name')->get(),
            'statuses' => ActivityStatus::cases(),
            'completeness' => $this->completenessService->evaluate($activity),
            'publicAttendanceUrl' => $publicAttendanceUrl,
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): RedirectResponse
    {
        $user = $request->user();
        $oldValues = $activity->only([
            'title', 'category_id', 'status', 'activity_date', 'start_time', 'end_time',
            'location', 'unit_id', 'pic_name', 'pic_contact', 'description', 'objective',
            'target_participants', 'notes',
        ]);

        $unitId = $user->isSuperadmin()
            ? $request->integer('unit_id')
            : (int) $activity->unit_id;

        $activity->fill([
            'title' => $request->string('title')->toString(),
            'category_id' => $request->integer('category_id'),
            'status' => $request->enum('status', ActivityStatus::class),
            'activity_date' => $request->string('activity_date')->toString(),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'location' => $request->string('location')->toString(),
            'unit_id' => $unitId,
            'pic_name' => $request->string('pic_name')->toString(),
            'pic_contact' => $request->input('pic_contact'),
            'description' => $request->input('description'),
            'objective' => $request->input('objective'),
            'target_participants' => $request->input('target_participants'),
            'notes' => $request->input('notes'),
            'updated_by' => $user->id,
        ])->save();

        $this->auditLog->logModelChange(
            'activity_updated',
            $activity,
            $oldValues,
            $activity->only([
                'title', 'category_id', 'status', 'activity_date', 'start_time', 'end_time',
                'location', 'unit_id', 'pic_name', 'pic_contact', 'description', 'objective',
                'target_participants', 'notes',
            ]),
            $request,
        );

        return redirect()
            ->route('activities.edit', $activity)
            ->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        DB::transaction(function () use ($activity, $request) {
            $oldValues = $activity->only(['title', 'status', 'activity_date', 'unit_id']);
            $activityId = $activity->id;
            $activity->delete();

            $this->fileStorage->purgeActivityFiles($activityId);

            $this->auditLog->log(
                'activity_deleted',
                'activity',
                $activityId,
                $oldValues,
                null,
                $request,
            );
        });

        return redirect()
            ->route('activities.index')
            ->with('success', 'Kegiatan berhasil dihapus.');
    }
}
