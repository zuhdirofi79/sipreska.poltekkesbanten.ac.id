<?php

namespace App\Http\Controllers;

use App\Http\Requests\Activity\StoreOfflineAttendanceImportRequest;
use App\Http\Requests\Activity\UpdateOfflineAttendanceImportReviewRequest;
use App\Models\Activity;
use App\Models\OfflineAttendanceImport;
use App\Services\AttendanceService;
use App\Services\OfflineAttendanceImportCommitService;
use App\Services\OfflineAttendanceImportService;
use App\Services\OfflineAttendanceTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfflineAttendanceImportController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly OfflineAttendanceTemplateService $templateService,
        private readonly OfflineAttendanceImportService $importService,
        private readonly OfflineAttendanceImportCommitService $commitService,
    ) {}

    public function downloadTemplate(Request $request, Activity $activity): Response
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $activity->loadMissing('unit');
        $rows = $request->integer('rows', 30);

        return $this->templateService->download($activity, $form, $rows);
    }

    public function create(Activity $activity): View
    {
        $this->authorize('manageAttendance', $activity);

        $form = $this->attendanceService->ensureForm($activity);
        $imports = OfflineAttendanceImport::query()
            ->where('activity_id', $activity->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('activities.attendance.offline-import-create', [
            'activity' => $activity,
            'form' => $form,
            'imports' => $imports,
        ]);
    }

    public function store(StoreOfflineAttendanceImportRequest $request, Activity $activity): RedirectResponse
    {
        $form = $this->attendanceService->ensureForm($activity);

        $import = $this->importService->createFromUpload(
            $activity,
            $form,
            $request->file('scan'),
            $request->user(),
        );

        return redirect()
            ->route('activities.daftar-hadir.import.review', [$activity, $import])
            ->with('success', 'File scan berhasil diupload. Silakan review data sebelum import final.');
    }

    public function review(Activity $activity, OfflineAttendanceImport $import): View
    {
        $this->authorize('manageAttendance', $activity);
        $this->assertImportBelongsToActivity($activity, $import);

        $import->load(['rows', 'uploader']);
        $form = $this->attendanceService->ensureForm($activity);

        return view('activities.attendance.offline-import-review', [
            'activity' => $activity,
            'form' => $form,
            'import' => $import,
            'workUnits' => \App\Enums\AttendanceWorkUnit::cases(),
            'previewUrl' => route('activities.daftar-hadir.import.preview', [$activity, $import]),
            'editable' => $import->isEditable(),
        ]);
    }

    public function preview(Activity $activity, OfflineAttendanceImport $import): StreamedResponse|Response
    {
        $this->authorize('manageAttendance', $activity);
        $this->assertImportBelongsToActivity($activity, $import);

        $disk = $import->stored_disk ?: 'local';
        $path = $import->stored_path;

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, $import->original_filename, [
            'Content-Type' => $import->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$import->original_filename.'"',
        ]);
    }

    public function updateReview(
        UpdateOfflineAttendanceImportReviewRequest $request,
        Activity $activity,
        OfflineAttendanceImport $import,
    ): RedirectResponse {
        $this->assertImportBelongsToActivity($activity, $import);

        try {
            $this->importService->updateReview($import, $request->reviewPayload());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        if ($request->boolean('add_row')) {
            $this->importService->addBlankRow($import);

            return back()->with('success', 'Baris baru ditambahkan.');
        }

        return back()->with('success', 'Perubahan review disimpan.');
    }

    public function commit(Activity $activity, OfflineAttendanceImport $import): RedirectResponse
    {
        $this->authorize('manageAttendance', $activity);
        $this->assertImportBelongsToActivity($activity, $import);

        try {
            $result = $this->commitService->commit($import);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()
            ->route('activities.daftar-hadir.responses', $activity)
            ->with(
                'success',
                "Import final selesai: {$result['imported']} data masuk, "
                ."{$result['skipped_duplicates']} duplikat dilewati, "
                ."{$result['skipped_invalid']} baris tidak valid."
            );
    }

    public function destroy(Activity $activity, OfflineAttendanceImport $import): RedirectResponse
    {
        $this->authorize('manageAttendance', $activity);
        $this->assertImportBelongsToActivity($activity, $import);

        if ($import->status === OfflineAttendanceImport::STATUS_IMPORTED) {
            return back()->withErrors(['form' => 'Import yang sudah selesai tidak dapat dihapus.']);
        }

        $this->importService->destroy($import);

        return redirect()
            ->route('activities.daftar-hadir.import.create', $activity)
            ->with('success', 'Draft import dihapus.');
    }

    private function assertImportBelongsToActivity(Activity $activity, OfflineAttendanceImport $import): void
    {
        if ((int) $import->activity_id !== (int) $activity->id) {
            abort(404);
        }
    }
}
