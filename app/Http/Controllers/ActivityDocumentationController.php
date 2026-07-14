<?php

namespace App\Http\Controllers;

use App\Enums\DocumentationType;
use App\Http\Requests\Activity\StoreDocumentationRequest;
use App\Http\Requests\Activity\UpdateDocumentationRequest;
use App\Models\Activity;
use App\Models\Documentation;
use App\Services\AuditLogService;
use App\Services\FileStorageService;
use App\Services\ImageProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ActivityDocumentationController extends Controller
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly ImageProcessingService $imageProcessing,
        private readonly AuditLogService $auditLog,
    ) {}

    public function store(StoreDocumentationRequest $request, Activity $activity): RedirectResponse
    {
        try {
            $stored = $this->fileStorage->storeUploadedFile(
                $request->file('file'),
                'documentation',
                $activity->id,
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }

        $thumbnailPath = null;

        if (str_starts_with($stored['mime_type'], 'image/')) {
            $thumbnailPath = $this->imageProcessing->createThumbnail(
                $stored['path'],
                $this->fileStorage->activityDirectory('documentation', $activity->id).'/thumbnails',
                $activity->id,
            );
        }

        DB::transaction(function () use ($request, $activity, $stored, $thumbnailPath) {
            Documentation::query()->create([
                'activity_id' => $activity->id,
                'title' => $request->input('title'),
                'type' => $request->enum('type', DocumentationType::class),
                'description' => $request->input('description'),
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'thumbnail_path' => $thumbnailPath,
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'uploaded_by' => $request->user()?->id,
            ]);

            $this->auditLog->log(
                'documentation_uploaded',
                'activity',
                $activity->id,
                null,
                ['type' => $request->input('type')],
                $request,
            );
        });

        return back()->with('success', 'Dokumentasi berhasil diunggah.');
    }

    public function update(UpdateDocumentationRequest $request, Activity $activity, Documentation $documentation): RedirectResponse
    {
        if ((int) $documentation->activity_id !== (int) $activity->id) {
            abort(404);
        }

        $documentation->fill([
            'title' => $request->input('title'),
            'type' => $request->enum('type', DocumentationType::class),
            'description' => $request->input('description'),
        ])->save();

        $this->auditLog->logModelChange(
            'documentation_updated',
            $documentation,
            null,
            $documentation->only(['title', 'type', 'description']),
            $request,
        );

        return back()->with('success', 'Dokumentasi berhasil diperbarui.');
    }

    public function view(Activity $activity, Documentation $documentation): StreamedResponse
    {
        $document = $this->resolveDocument($activity, $documentation);

        return $this->fileStorage->stream(
            $document->path,
            $document->mime_type,
            $document->original_name,
            inline: true,
            disk: $document->disk,
        );
    }

    public function download(Activity $activity, Documentation $documentation): StreamedResponse
    {
        $document = $this->resolveDocument($activity, $documentation);

        return $this->fileStorage->stream(
            $document->path,
            $document->mime_type,
            $document->original_name,
            inline: false,
            disk: $document->disk,
        );
    }

    public function thumbnail(Activity $activity, Documentation $documentation): StreamedResponse
    {
        $document = $this->resolveDocument($activity, $documentation);

        if ($document->thumbnail_path === null) {
            return $this->view($activity, $documentation);
        }

        return $this->fileStorage->stream(
            $document->thumbnail_path,
            'image/jpeg',
            'thumb-'.$document->original_name,
            inline: true,
            disk: $document->disk,
        );
    }

    public function destroy(Activity $activity, Documentation $documentation): RedirectResponse
    {
        $this->authorize('manageDocuments', $activity);

        if ((int) $documentation->activity_id !== (int) $activity->id) {
            abort(404);
        }

        DB::transaction(function () use ($documentation, $activity) {
            $path = $documentation->path;
            $thumbnail = $documentation->thumbnail_path;
            $documentation->delete();
            $this->fileStorage->delete($path);
            $this->fileStorage->delete($thumbnail);

            $this->auditLog->log('documentation_deleted', 'activity', $activity->id, null, null, request());
        });

        return back()->with('success', 'Dokumentasi berhasil dihapus.');
    }

    private function resolveDocument(Activity $activity, Documentation $documentation): Documentation
    {
        $this->authorize('view', $activity);

        if ((int) $documentation->activity_id !== (int) $activity->id) {
            abort(404);
        }

        return $documentation;
    }
}
