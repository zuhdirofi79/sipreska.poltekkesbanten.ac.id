<?php

namespace App\Http\Controllers;

use App\Models\ExternalActivity;
use App\Models\ExternalAttendanceParticipant;
use App\Services\ExternalAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExternalAttendanceFileController extends Controller
{
    public function signature(
        Request $request,
        ExternalActivity $externalActivity,
        ExternalAttendanceParticipant $participant,
    ): StreamedResponse {
        $this->authorize('manageAttendance', $externalActivity);

        if ((int) $participant->external_activity_id !== (int) $externalActivity->id) {
            abort(404);
        }

        $path = $this->safeSignaturePath((string) $participant->signature_path);
        $disk = $participant->signature_disk ?: (string) config('sipreska.document_disk', 'sipreska');

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $inline = $request->boolean('inline', true);
        $filename = 'signature-external-'.$participant->id.'.png';
        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($filename).'"';

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeSignaturePath(string $path): string
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $prefix = trim((string) config('sipreska.document_path_prefix', 'docs'), '/');
        $allowedPrefix = $prefix.'/'.ExternalAttendanceService::SIGNATURE_FOLDER.'/';

        if ($normalized === '' || str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            throw new RuntimeException('Path tidak valid.');
        }

        if (! str_starts_with($normalized, $allowedPrefix)) {
            abort(404);
        }

        return $normalized;
    }
}
