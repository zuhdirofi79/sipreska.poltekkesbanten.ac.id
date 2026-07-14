<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Terdaftar = 'terdaftar';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Terdaftar => 'Terdaftar',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Terdaftar => 'sipreska-badge-blue',
            self::Selesai => 'sipreska-badge-green',
            self::Batal => 'sipreska-badge-red',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Terdaftar => '#2563eb',
            self::Selesai => '#16a34a',
            self::Batal => '#dc2626',
        };
    }
}
