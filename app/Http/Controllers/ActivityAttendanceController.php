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
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                'nip_nik' => $response->nip_nik,
                'position' => $response->position,
                'work_unit' => AttendanceWorkUnit::tryFrom($response->work_unit)?->label() ?? $response->work_unit,
                'institution_name' => $response->institution_name,
                'submitted_at' => $response->submitted_at?->timezone(config('sipreska.display_timezone'))->format('d/m/Y H:i:s'),
            ]),
        ]);
    }

    public function qr(Activity $activity): Response
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $url = route('attendance.public.show', ['public_token' => $form->public_token]);

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 8,
            'imageTransparent' => false,
        ]);

        $png = (new QRCode($options))->render($url);

        return response($png, 200, ['Content-Type' => 'image/png']);
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

        $normalized = $this->attendanceService->normalizeNipNik($request->string('nip_nik')->toString());

        $duplicate = AttendanceResponse::query()
            ->where('attendance_form_id', $form->id)
            ->where('nip_nik_normalized', $normalized)
            ->where('id', '!=', $response->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['nip_nik' => 'NIP/NIK sudah terdaftar pada kegiatan ini.'])->withInput();
        }

        $oldValues = $response->only(['name', 'nip_nik', 'position', 'work_unit', 'institution_name']);

        $response->fill([
            'name' => $request->string('name')->toString(),
            'nip_nik' => $request->string('nip_nik')->toString(),
            'nip_nik_normalized' => $normalized,
            'position' => $request->input('position'),
            'work_unit' => $request->string('work_unit')->toString(),
            'institution_name' => $request->input('institution_name'),
        ])->save();

        $this->auditLog->logModelChange(
            'attendance_response_updated',
            $response,
            $oldValues,
            $response->only(['name', 'nip_nik', 'position', 'work_unit', 'institution_name']),
            $request,
        );

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
            ->orderBy('submitted_at')
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
