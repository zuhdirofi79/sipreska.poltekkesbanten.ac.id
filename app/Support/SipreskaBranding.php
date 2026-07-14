<?php

namespace App\Support;

class SipreskaBranding
{
    public static function logoPath(): string
    {
        return (string) config('sipreska.logo_path', 'images/logo-poltekkes-banten.png');
    }

    public static function logoExists(): bool
    {
        return is_file(public_path(static::logoPath()));
    }

    public static function logoUrl(): string
    {
        return asset(static::logoPath());
    }
}
