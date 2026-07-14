<?php

namespace App\Http\Controllers;

use App\Http\Requests\Activity\StoreMinutesRequest;
use App\Models\Activity;
use App\Models\MinutesDocument;
use App\Services\AuditLogService;
use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ActivityMinutesController extends Controller
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly AuditLogService $auditLog,
    ) {}

    public function store(StoreMinutesRequest $request, Activity $activity): RedirectResponse
    {
        $existing = $activity->minutesDocument;
        $oldPath = $existing?->path;

        try {
            $stored = $this->fileStorage->storeUploadedFile(
                $request->file('file'),
                'minutes',
                $activity->id,
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }

        DB::transaction(function () use ($request, $activity, $stored, $existing) {
            if ($existing !== null) {
                $existing->delete();
            }

            MinutesDocument::query()->create([
                'activity_id' => $activity->id,
                'minutes_date' => $request->input('minutes_date'),
                'notulist_name' => $request->input('notulist_name'),
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'uploaded_by' => $request->user()?->id,
            ]);

            $this->auditLog->log(
                'minutes_uploaded',
                'activity',
                $activity->id,
                null,
                ['notulist_name' => $request->input('notulist_name')],
                $request,
            );
        });

        if ($oldPath !== null) {
            $this->fileStorage->delete($oldPath);
        }

        return back()->with('success', 'Notulensi berhasil diunggah.');
    }

    public function view(Activity $activity): StreamedResponse
    {
        $document = $this->resolveDocument($activity);

        if ($document->mime_type !== 'application/pdf') {
            return $this->download($activity);
        }

        return $this->fileStorage->stream(
            $document->path,
            $document->mime_type,
            $document->original_name,
            inline: true,
            disk: $document->disk,
        );
    }

    public function download(Activity $activity): StreamedResponse
    {
        $document = $this->resolveDocument($activity);

        return $this->fileStorage->stream(
            $document->path,
            $document->mime_type,
            $document->original_name,
            inline: false,
            disk: $document->disk,
        );
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorize('manageDocuments', $activity);

        $document = $activity->minutesDocument;

        if ($document === null) {
            return back()->with('error', 'Notulensi tidak ditemukan.');
        }

        DB::transaction(function () use ($document, $activity) {
            $path = $document->path;
            $document->delete();
            $this->fileStorage->delete($path);

            $this->auditLog->log('minutes_deleted', 'activity', $activity->id, null, null, request());
        });

        return back()->with('success', 'Notulensi berhasil dihapus.');
    }

    private function resolveDocument(Activity $activity): MinutesDocument
    {
        $this->authorize('view', $activity);

        $document = $activity->minutesDocument;

        if ($document === null) {
            abort(404);
        }

        return $document;
    }
}
