<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceWorkUnit;
use App\Http\Requests\AttendanceResponseFilterRequest;
use App\Models\Activity;
use App\Models\AttendanceResponse;
use App\Services\AttendanceService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AttendanceResponseController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(AttendanceResponseFilterRequest $request, Activity $activity): View
    {
        $this->authorize('manageAttendance', $activity);

        $activity->load(['category', 'unit', 'attendanceForm']);
        $form = $this->attendanceService->ensureForm($activity);
        $filters = $request->filters();

        $responses = $this->attendanceService
            ->responsesQuery($form, $filters)
            ->paginate(25)
            ->withQueryString();

        $stats = $this->attendanceService->responseStats($form);

        return view('attendance.responses.index', [
            'activity' => $activity,
            'form' => $form,
            'responses' => $responses,
            'filters' => $filters,
            'stats' => $stats,
            'workUnits' => AttendanceWorkUnit::cases(),
            'exportQuery' => http_build_query(array_filter($filters)),
        ]);
    }

    public function show(Activity $activity, AttendanceResponse $response): View
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);

        if ((int) $response->attendance_form_id !== (int) $form->id) {
            abort(404);
        }

        $this->auditLog->log(
            'attendance_response_viewed',
            'attendance_response',
            $response->id,
            null,
            ['activity_id' => $activity->id],
            request(),
        );

        $activity->load(['unit']);

        return view('attendance.responses.show', [
            'activity' => $activity,
            'response' => $response,
            'workUnitLabel' => AttendanceWorkUnit::tryFrom($response->work_unit)?->label() ?? $response->work_unit,
        ]);
    }

    public function data(AttendanceResponseFilterRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);

        return response()->json($this->attendanceService->responsePollPayload($form));
    }
}
