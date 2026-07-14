<?php

namespace App\Enums;

enum DocumentationType: string
{
    case FotoKegiatan = 'foto_kegiatan';
    case Banner = 'banner';
    case Poster = 'poster';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::FotoKegiatan => 'Foto Kegiatan',
            self::Banner => 'Banner',
            self::Poster => 'Poster',
            self::Lainnya => 'Dokumentasi Lainnya',
        };
    }
}
