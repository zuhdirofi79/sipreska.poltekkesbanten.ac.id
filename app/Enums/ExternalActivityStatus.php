<?php

namespace App\Enums;

enum ExternalActivityStatus: string
{
    case Terdaftar = 'terdaftar';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public const CALENDAR_COLOR = '#7c3aed';

    public function label(): string
    {
        return match ($this) {
            self::Terdaftar => 'Terdaftar',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Terdaftar => 'blue',
            self::Selesai => 'green',
            self::Batal => 'red',
        };
    }

    public function calendarColor(): string
    {
        return self::CALENDAR_COLOR;
    }
}
