<?php

namespace App\Support;

use App\Models\AttendanceResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves attendance signature files for PDF/Excel export.
 *
 * Signatures are stored as PNG files on the sipreska disk
 * (signature_disk + signature_path). Admin preview uses the authorized
 * files.show route; DomPDF needs a local/base64 image source instead.
 */
class AttendanceSignatureExport
{
    public static function exists(AttendanceResponse $response): bool
    {
        $path = $response->signature_path;

        if ($path === null || $path === '') {
            return false;
        }

        return Storage::disk(static::disk($response))->exists($path);
    }

    /**
     * DomPDF-friendly image source (data URI). Prefer local file contents
     * over public HTTP URLs so export works offline and without auth cookies.
     */
    public static function pdfSrc(AttendanceResponse $response): ?string
    {
        if (! static::exists($response)) {
            return null;
        }

        $contents = Storage::disk(static::disk($response))->get($response->signature_path);

        if ($contents === null || $contents === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    /**
     * OpenSpout XLSX writer has no image/drawing API in this project version.
     * Do not dump file paths or base64 into cells — use a clear indicator.
     */
    public static function excelLabel(AttendanceResponse $response): string
    {
        return static::exists($response) ? 'Ada' : '-';
    }

    private static function disk(AttendanceResponse $response): string
    {
        return $response->signature_disk
            ?: (string) config('sipreska.document_disk', 'sipreska');
    }
}
