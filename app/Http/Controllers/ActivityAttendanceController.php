<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceWorkUnit;
use App\Http\Requests\AttendanceResponseFilterRequest;
use App\Http\Requests\Activity\UpdateAttendanceResponseRequest;
use App\Http\Requests\Activity\UpdateAttendanceSettingsRequest;
use App\Models\Activity;
use App\Models\AttendanceResponse;
use App\Services\AttendanceExportService;
use App\Services\AttendanceService;
use App\Services\AuditLogService;
use App\Support\AttendanceQrImage;
use App\Support\AttendancePublicRoutes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceExportService $exportService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Activity $activity): RedirectResponse
    {
        $this->authorize('manageAttendance', $activity);

        return redirect()->route('activities.edit', [$activity, 'tab' => 'attendance']);
    }

    public function store(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('manageAttendance', $activity);

        $request->merge([
            'is_open' => $request->boolean('is_open'),
            'signature_required' => $request->boolean('signature_required'),
        ]);

        $validated = $request->validate([
            'is_open' => ['required', 'boolean'],
            'signature_required' => ['required', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ]);

        $form = $this->attendanceService->ensureForm($activity);
        $this->attendanceService->updateSettings($form, $validated, $request);

        if ($validated['is_open']) {
            $this->attendanceService->open($form, $request);
        } else {
            $this->attendanceService->close($form, $request);
        }

        return back()->with('success', 'Form presensi berhasil disimpan.');
    }

    public function responses(Activity $activity): RedirectResponse
    {
        return redirect()->route('activities.daftar-hadir.responses', $activity);
    }

    public function export(Activity $activity): StreamedResponse
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);

        return $this->exportService->exportExcel($activity, $form);
    }

    public function updateSettings(UpdateAttendanceSettingsRequest $request, Activity $activity): RedirectResponse
    {
        $form = $this->attendanceService->ensureForm($activity);
        $this->attendanceService->updateSettings($form, $request->validated(), $request);

        return back()->with('success', 'Pengaturan absensi berhasil diperbarui.');
    }

    public function open(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $this->attendanceService->open($form, $request);

        return back()->with('success', 'Formulir absensi dibuka.');
    }

    public function close(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $this->attendanceService->close($form, $request);

        return back()->with('success', 'Formulir absensi ditutup.');
    }

    public function poll(Activity $activity): JsonResponse
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $responses = $form->responses()->orderByDesc('submitted_at')->get();

        return response()->json([
            'is_open' => $form->is_open,
            'is_accepting' => $form->isAcceptingSubmissions(),
            'count' => $responses->count(),
            'responses' => $responses->map(fn (AttendanceResponse $response) => [
                'id' => $response->id,
                'name' => $response->name,
                'nip' => $response->nip,
                'legacy_identity' => $response->legacyIdentity(),
                'position' => $response->position,
                'work_unit' => AttendanceWorkUnit::tryFrom($response->work_unit)?->label() ?? $response->work_unit,
                'institution_name' => $response->institution_name,
                'submitted_at' => $response->submitted_at?->timezone(config('sipreska.display_timezone'))->format('d/m/Y H:i:s'),
            ]),
        ]);
    }

    public function qr(Request $request, Activity $activity): Response
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $url = AttendancePublicRoutes::showUrlForForm($form)
            ?? route('attendance.public.show', ['public_token' => $form->public_token]);

        $forDownload = $request->has('size');
        $size = $forDownload ? $request->integer('size', 512) : null;
        $png = AttendanceQrImage::generate($url, $forDownload, $size);

        $headers = ['Content-Type' => 'image/png'];

        if ($forDownload) {
            $slug = Str::slug($activity->title) ?: 'kegiatan';
            $headers['Content-Disposition'] = 'attachment; filename="qr-daftar-hadir-'.$slug.'.png"';
        }

        return response($png, 200, $headers);
    }

    public function exportExcel(Activity $activity, AttendanceResponseFilterRequest $request): StreamedResponse
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $filters = $request->filters();

        $this->auditLog->log(
            'attendance_export_excel',
            'activity',
            $activity->id,
            null,
            ['filters' => array_filter($filters)],
            $request,
        );

        return $this->exportService->exportExcel($activity, $form, $filters);
    }

    public function exportPdf(Activity $activity, AttendanceResponseFilterRequest $request): Response
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $filters = $request->filters();

        $this->auditLog->log(
            'attendance_export_pdf',
            'activity',
            $activity->id,
            null,
            ['filters' => array_filter($filters)],
            $request,
        );

        return $this->exportService->exportPdf($activity, $form, $filters);
    }

    public function editResponse(Activity $activity, AttendanceResponse $response): View
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);

        if ((int) $response->attendance_form_id !== (int) $form->id) {
            abort(404);
        }

        return view('activities.attendance.edit-response', [
            'activity' => $activity,
            'response' => $response,
            'workUnits' => AttendanceWorkUnit::cases(),
        ]);
    }

    public function updateResponse(UpdateAttendanceResponseRequest $request, Activity $activity, AttendanceResponse $response): RedirectResponse
    {
        $form = $this->attendanceService->ensureForm($activity);

        if ((int) $response->attendance_form_id !== (int) $form->id) {
            abort(404);
        }

        try {
            $this->attendanceService->updateResponse($response, $request->validated(), $request);
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'sudah tercatat untuk kegiatan ini')) {
                return back()->withErrors(['form' => $exception->getMessage()])->withInput();
            }

            throw $exception;
        }

        return redirect()
            ->route('activities.daftar-hadir.responses', $activity)
            ->with('success', 'Data peserta berhasil diperbarui.');
    }

    public function print(Activity $activity, AttendanceResponseFilterRequest $request): View
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $filters = $request->filters();

        $this->auditLog->log(
            'attendance_print',
            'activity',
            $activity->id,
            null,
            ['filters' => array_filter($filters)],
            $request,
        );

        $responses = $this->attendanceService
            ->responsesQuery($form, $filters)
            ->get();

        return view('exports.attendance-pdf', [
            'activity' => $activity,
            'responses' => $responses,
            'generatedAt' => now()->timezone(config('sipreska.display_timezone'))->format('d/m/Y H:i:s'),
            'printMode' => true,
        ]);
    }

    public function destroyResponse(Request $request, Activity $activity, AttendanceResponse $response): RedirectResponse
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);

        if ((int) $response->attendance_form_id !== (int) $form->id) {
            abort(404);
        }

        $this->attendanceService->deleteResponse($response, $request);

        return back()->with('success', 'Data peserta berhasil dihapus.');
    }
}
