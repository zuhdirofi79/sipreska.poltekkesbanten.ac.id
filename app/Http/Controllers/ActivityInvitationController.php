<?php

namespace App\Http\Controllers;

use App\Http\Requests\Activity\StoreInvitationRequest;
use App\Models\Activity;
use App\Models\InvitationDocument;
use App\Services\AuditLogService;
use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ActivityInvitationController extends Controller
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly AuditLogService $auditLog,
    ) {}

    public function store(StoreInvitationRequest $request, Activity $activity): RedirectResponse
    {
        $existing = $activity->invitationDocument;
        $oldPath = $existing?->path;

        try {
            $stored = $this->fileStorage->storeUploadedFile(
                $request->file('file'),
                'invitation',
                $activity->id,
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }

        DB::transaction(function () use ($request, $activity, $stored, $existing) {
            if ($existing !== null) {
                $existing->delete();
            }

            InvitationDocument::query()->create([
                'activity_id' => $activity->id,
                'letter_number' => $request->input('letter_number'),
                'letter_date' => $request->input('letter_date'),
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'uploaded_by' => $request->user()?->id,
            ]);

            $this->auditLog->log(
                'invitation_uploaded',
                'activity',
                $activity->id,
                null,
                ['letter_number' => $request->input('letter_number')],
                $request,
            );
        });

        if ($oldPath !== null) {
            $this->fileStorage->delete($oldPath);
        }

        return back()->with('success', 'Surat undangan berhasil diunggah.');
    }

    public function view(Activity $activity): StreamedResponse
    {
        $document = $this->resolveDocument($activity);

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

        $document = $activity->invitationDocument;

        if ($document === null) {
            return back()->with('error', 'Surat undangan tidak ditemukan.');
        }

        DB::transaction(function () use ($document, $activity) {
            $path = $document->path;
            $document->delete();
            $this->fileStorage->delete($path);

            $this->auditLog->log('invitation_deleted', 'activity', $activity->id, null, null, request());
        });

        return back()->with('success', 'Surat undangan berhasil dihapus.');
    }

    private function resolveDocument(Activity $activity): InvitationDocument
    {
        $this->authorize('view', $activity);

        $document = $activity->invitationDocument;

        if ($document === null) {
            abort(404);
        }

        return $document;
    }
}
