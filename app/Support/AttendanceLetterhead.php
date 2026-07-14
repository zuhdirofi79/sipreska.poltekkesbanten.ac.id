<?php

namespace App\Support;

class AttendanceLetterhead
{
    public static function path(): string
    {
        return (string) config('sipreska.attendance_letterhead_path', 'images/daftar-hadir-header.jpeg');
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

        $contents = file_get_contents(public_path(static::path()));

        if ($contents === false) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($contents);
    }
}
