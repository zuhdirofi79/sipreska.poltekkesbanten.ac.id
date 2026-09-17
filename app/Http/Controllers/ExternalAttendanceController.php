<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExternalActivity\ExternalAttendanceFilterRequest;
use App\Http\Requests\ExternalActivity\UpdateExternalAttendanceParticipantRequest;
use App\Models\ExternalActivity;
use App\Models\ExternalAttendanceParticipant;
use App\Services\ExternalAttendanceExportService;
use App\Services\ExternalAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExternalAttendanceController extends Controller
{
    public function __construct(
        private readonly ExternalAttendanceService $attendanceService,
        private readonly ExternalAttendanceExportService $exportService,
    ) {}

    public function index(ExternalAttendanceFilterRequest $request, ExternalActivity $externalActivity): View
    {
        $this->authorize('manageAttendance', $externalActivity);

        $externalActivity->load(['dates', 'questions']);
        $filters = $request->filters();

        $participants = $this->attendanceService
            ->participantsQuery($externalActivity, $filters)
            ->paginate(25)
            ->withQueryString();

        return view('external-activities.attendance.index', [
            'activity' => $externalActivity,
            'participants' => $participants,
            'filters' => $filters,
            'stats' => $this->attendanceService->participantStats($externalActivity),
            'exportQuery' => http_build_query(array_filter($filters)),
        ]);
    }

    public function show(ExternalActivity $externalActivity, ExternalAttendanceParticipant $participant): View
    {
        $this->authorize('manageAttendance', $externalActivity);
        $this->ensureParticipantBelongsToActivity($externalActivity, $participant);

        $participant->load(['attendanceDates', 'answers.question']);

        return view('external-activities.attendance.show', [
            'activity' => $externalActivity,
            'participant' => $participant,
        ]);
    }

    public function edit(ExternalActivity $externalActivity, ExternalAttendanceParticipant $participant): View
    {
        $this->authorize('manageAttendance', $externalActivity);
        $this->ensureParticipantBelongsToActivity($externalActivity, $participant);

        $externalActivity->load(['dates', 'questions']);
        $participant->load(['attendanceDates', 'answers.question']);

        return view('external-activities.attendance.edit', [
            'activity' => $externalActivity,
            'participant' => $participant,
        ]);
    }

    public function update(
        UpdateExternalAttendanceParticipantRequest $request,
        ExternalActivity $externalActivity,
        ExternalAttendanceParticipant $participant,
    ): RedirectResponse {
        $this->authorize('manageAttendance', $externalActivity);
        $this->ensureParticipantBelongsToActivity($externalActivity, $participant);

        try {
            $this->attendanceService->updateParticipant($participant, $request->validated(), $request);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['email' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('external-activities.attendance.show', [$externalActivity, $participant])
            ->with('success', 'Data peserta berhasil dikoreksi.');
    }

    public function destroy(
        Request $request,
        ExternalActivity $externalActivity,
        ExternalAttendanceParticipant $participant,
    ): RedirectResponse {
        $this->authorize('manageAttendance', $externalActivity);
        $this->ensureParticipantBelongsToActivity($externalActivity, $participant);

        $this->attendanceService->deleteParticipant($participant, $request);

        return redirect()
            ->route('external-activities.attendance.index', $externalActivity)
            ->with('success', 'Data peserta berhasil dihapus.');
    }

    public function exportExcel(ExternalAttendanceFilterRequest $request, ExternalActivity $externalActivity): StreamedResponse
    {
        $this->authorize('manageAttendance', $externalActivity);

        $externalActivity->load(['dates', 'questions']);

        return $this->exportService->exportExcel($externalActivity, $request->filters());
    }

    public function exportPdf(ExternalAttendanceFilterRequest $request, ExternalActivity $externalActivity): Response
    {
        $this->authorize('manageAttendance', $externalActivity);

        $externalActivity->load(['dates', 'questions']);

        return $this->exportService->exportPdf($externalActivity, $request->filters());
    }

    private function ensureParticipantBelongsToActivity(
        ExternalActivity $activity,
        ExternalAttendanceParticipant $participant,
    ): void {
        if ((int) $participant->external_activity_id !== (int) $activity->id) {
            abort(404);
        }
    }
}
