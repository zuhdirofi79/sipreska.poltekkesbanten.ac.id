<?php

namespace App\Enums;

enum PublicationPlatform: string
{
    case Website = 'website';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case MediaOnline = 'media_online';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::MediaOnline => 'Media Online',
            self::Lainnya => 'Lainnya',
        };
    }
}
