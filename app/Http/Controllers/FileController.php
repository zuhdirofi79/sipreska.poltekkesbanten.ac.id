<?php

namespace App\Http\Controllers;

use App\Models\Documentation;
use App\Models\InvitationDocument;
use App\Models\MinutesDocument;
use App\Models\AttendanceResponse;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
    ) {}

    public function show(Request $request, string $type, int $id): StreamedResponse
    {
        [$disk, $path, $mimeType, $filename] = match ($type) {
            'invitation' => $this->resolveInvitation($id),
            'minutes' => $this->resolveMinutes($id),
            'documentation' => $this->resolveDocumentation($id, $request->boolean('thumbnail')),
            'signature' => $this->resolveSignature($id),
            default => abort(404),
        };

        $inline = $request->boolean('inline', $type !== 'signature');

        return $this->fileStorage->stream($path, $mimeType, $filename, $inline, $disk);
    }

    private function resolveInvitation(int $id): array
    {
        $document = InvitationDocument::query()->with('activity')->findOrFail($id);
        $this->authorize('view', $document->activity);

        return [
            $document->disk,
            $document->path,
            $document->mime_type,
            $document->original_name,
        ];
    }

    private function resolveMinutes(int $id): array
    {
        $document = MinutesDocument::query()->with('activity')->findOrFail($id);
        $this->authorize('view', $document->activity);

        return [
            $document->disk,
            $document->path,
            $document->mime_type,
            $document->original_name,
        ];
    }

    private function resolveDocumentation(int $id, bool $thumbnail): array
    {
        $document = Documentation::query()->with('activity')->findOrFail($id);
        $this->authorize('view', $document->activity);

        if ($thumbnail && $document->thumbnail_path) {
            return [
                $document->disk,
                $document->thumbnail_path,
                'image/jpeg',
                'thumb-'.$document->original_name,
            ];
        }

        return [
            $document->disk,
            $document->path,
            $document->mime_type,
            $document->original_name,
        ];
    }

    private function resolveSignature(int $id): array
    {
        $response = AttendanceResponse::query()
            ->with(['form.activity'])
            ->findOrFail($id);

        $activity = $response->form?->activity;

        if ($activity === null) {
            abort(404);
        }

        $this->authorize('manageAttendance', $activity);

        return [
            $response->signature_disk,
            $response->signature_path,
            'image/png',
            'signature-'.$response->id.'.png',
        ];
    }
}
