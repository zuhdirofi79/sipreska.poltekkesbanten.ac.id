<?php

namespace App\Http\Controllers;

use App\Enums\ExternalActivityStatus;
use App\Http\Requests\ExternalActivity\StoreExternalActivityRequest;
use App\Http\Requests\ExternalActivity\UpdateExternalActivityRequest;
use App\Models\ExternalActivity;
use App\Services\AuditLogService;
use App\Services\ExternalActivityQuestionService;
use App\Services\ExternalAttendanceService;
use App\Support\ExternalAttendancePublicRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ExternalActivityController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly ExternalAttendanceService $attendanceService,
        private readonly ExternalActivityQuestionService $questionService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExternalActivity::class);

        $activities = ExternalActivity::query()
            ->with('dates')
            ->withCount('participants')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where('title', 'like', $search);
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('external-activities.index', [
            'activities' => $activities,
            'statuses' => ExternalActivityStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ExternalActivity::class);

        return view('external-activities.create', [
            'statuses' => ExternalActivityStatus::cases(),
            'defaultQuestions' => $this->questionService->defaultQuestionTemplates(),
        ]);
    }

    public function store(StoreExternalActivityRequest $request): RedirectResponse
    {
        $user = $request->user();

        $activity = DB::transaction(function () use ($request, $user) {
            $activity = ExternalActivity::query()->create([
                'title' => $request->string('title')->toString(),
                'location_address' => $request->validated('location_address'),
                'online_url' => $request->validated('online_url'),
                'status' => $request->enum('status', ExternalActivityStatus::class) ?? ExternalActivityStatus::Terdaftar,
                'is_open' => $request->boolean('is_open', true),
                'signature_required' => true,
                'use_custom_questions' => $request->boolean('use_custom_questions'),
                'opens_at' => $request->input('opens_at'),
                'closes_at' => $request->input('closes_at'),
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            $this->attendanceService->syncDates($activity, $request->input('dates', []));

            if ($activity->use_custom_questions) {
                $this->questionService->syncOrSeed($activity, $request->input('questions'));
            }

            $this->auditLog->logModelChange(
                'external_activity_created',
                $activity,
                null,
                $activity->only(['title', 'status', 'is_open', 'location_address', 'use_custom_questions']),
                $request,
            );

            return $activity;
        });

        return redirect()
            ->route('external-activities.show', $activity)
            ->with('success', 'Agenda kegiatan eksternal berhasil dibuat.');
    }

    public function show(ExternalActivity $externalActivity): View
    {
        $this->authorize('view', $externalActivity);

        $externalActivity->load(['dates', 'creator', 'updater', 'questions']);

        return view('external-activities.show', [
            'activity' => $externalActivity,
            'stats' => $this->attendanceService->participantStats($externalActivity),
            'publicAttendanceUrl' => ExternalAttendancePublicRoutes::showUrlForActivity($externalActivity),
        ]);
    }

    public function edit(ExternalActivity $externalActivity): View
    {
        $this->authorize('update', $externalActivity);

        $externalActivity->load([
            'dates' => fn ($query) => $query->withCount('participants'),
            'questions',
        ]);

        return view('external-activities.edit', [
            'activity' => $externalActivity,
            'statuses' => ExternalActivityStatus::cases(),
            'defaultQuestions' => $this->questionService->defaultQuestionTemplates(),
        ]);
    }

    public function update(UpdateExternalActivityRequest $request, ExternalActivity $externalActivity): RedirectResponse
    {
        $oldValues = $externalActivity->only(['title', 'status', 'is_open', 'opens_at', 'closes_at', 'location_address', 'use_custom_questions']);

        try {
            DB::transaction(function () use ($request, $externalActivity) {
                $externalActivity->fill([
                    'title' => $request->string('title')->toString(),
                    'location_address' => $request->validated('location_address'),
                    'online_url' => $request->validated('online_url'),
                    'status' => $request->enum('status', ExternalActivityStatus::class),
                    'opens_at' => $request->input('opens_at'),
                    'closes_at' => $request->input('closes_at'),
                    'updated_by' => $request->user()?->id,
                ]);

                if ($request->has('is_open')) {
                    $externalActivity->is_open = $request->boolean('is_open');
                }

                if ($request->has('use_custom_questions')) {
                    $externalActivity->use_custom_questions = $request->boolean('use_custom_questions');
                }

                $externalActivity->save();
                $this->attendanceService->syncDates($externalActivity, $request->input('dates', []));

                if ($externalActivity->use_custom_questions) {
                    $this->questionService->syncOrSeed($externalActivity, $request->input('questions'));
                }
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['dates' => $exception->getMessage()])->withInput();
        }

        $this->auditLog->logModelChange(
            'external_activity_updated',
            $externalActivity,
            $oldValues,
            $externalActivity->only(['title', 'status', 'is_open', 'opens_at', 'closes_at', 'location_address', 'use_custom_questions']),
            $request,
        );

        return redirect()
            ->route('external-activities.show', $externalActivity)
            ->with('success', 'Agenda kegiatan eksternal berhasil diperbarui.');
    }

    public function destroy(Request $request, ExternalActivity $externalActivity): RedirectResponse
    {
        $this->authorize('delete', $externalActivity);

        $oldValues = $externalActivity->only(['title', 'status']);
        $activityId = $externalActivity->id;
        $externalActivity->delete();

        $this->auditLog->log(
            'external_activity_deleted',
            'external_activity',
            $activityId,
            $oldValues,
            null,
            $request,
        );

        return redirect()
            ->route('external-activities.index')
            ->with('success', 'Agenda kegiatan eksternal berhasil dihapus.');
    }

    public function open(Request $request, ExternalActivity $externalActivity): RedirectResponse
    {
        $this->authorize('update', $externalActivity);
        $this->attendanceService->open($externalActivity, $request);

        return back()->with('success', 'Form absensi eksternal berhasil dibuka.');
    }

    public function close(Request $request, ExternalActivity $externalActivity): RedirectResponse
    {
        $this->authorize('update', $externalActivity);
        $this->attendanceService->close($externalActivity, $request);

        return back()->with('success', 'Form absensi eksternal berhasil ditutup.');
    }
}
