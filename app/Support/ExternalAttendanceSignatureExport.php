<?php

namespace App\Support;

use App\Models\ExternalAttendanceParticipant;
use Illuminate\Support\Facades\Storage;

class ExternalAttendanceSignatureExport
{
    public static function exists(ExternalAttendanceParticipant $participant): bool
    {
        $path = $participant->signature_path;

        if ($path === null || $path === '') {
            return false;
        }

        return Storage::disk(static::disk($participant))->exists($path);
    }

    public static function pdfSrc(ExternalAttendanceParticipant $participant): ?string
    {
        if (! static::exists($participant)) {
            return null;
        }

        $contents = Storage::disk(static::disk($participant))->get($participant->signature_path);

        if ($contents === null || $contents === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    public static function excelLabel(ExternalAttendanceParticipant $participant): string
    {
        return static::exists($participant) ? 'Ada' : '-';
    }

    private static function disk(ExternalAttendanceParticipant $participant): string
    {
        return $participant->signature_disk
            ?: (string) config('sipreska.document_disk', 'sipreska');
    }
}
