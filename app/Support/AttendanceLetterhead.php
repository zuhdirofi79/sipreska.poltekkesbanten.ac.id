<?php

namespace App\Support;

class AttendanceLetterhead
{
    /** @var list<string> */
    private const CANDIDATES = [
        'images/kop-surat-poltekkes-banten.png',
        'images/kop-surat-poltekkes-banten.jpeg',
        'images/kop-surat-poltekkes-banten.jpg',
        'images/daftar-hadir-header.jpeg',
        'images/daftar-hadir-header.jpg',
    ];

    public static function path(): string
    {
        $default = 'images/daftar-hadir-header.jpeg';
        $configured = (string) config('sipreska.attendance_letterhead_path', $default);

        if ($configured !== '' && is_file(public_path($configured))) {
            return $configured;
        }

        // Fall back to alternate local kop assets only when the default path is in use.
        if ($configured === $default || $configured === '') {
            foreach (self::CANDIDATES as $candidate) {
                if (is_file(public_path($candidate))) {
                    return $candidate;
                }
            }
        }

        return $configured !== '' ? $configured : $default;
    }

    public static function exists(): bool
    {
        return is_file(public_path(static::path()));
    }

    public static function assetUrl(): string
    {
        return asset(static::path());
    }

    public static function pdfSrc(): ?string
    {
        if (! static::exists()) {
            return null;
        }

        $absolute = public_path(static::path());
        $contents = file_get_contents($absolute);

        if ($contents === false) {
            return null;
        }

        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
