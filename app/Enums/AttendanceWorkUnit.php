<?php

namespace App\Enums;

enum AttendanceWorkUnit: string
{
    case Direktorat = 'direktorat';
    case D3Keperawatan = 'd3-keperawatan';
    case D3Kebidanan = 'd3-kebidanan';
    case D3Tlm = 'd3-tlm';
    case StrNers = 'str-ners';
    case InstansiLainnya = 'instansi_lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Direktorat => 'Direktorat',
            self::D3Keperawatan => 'D3 Keperawatan',
            self::D3Kebidanan => 'D3 Kebidanan',
            self::D3Tlm => 'D3 TLM',
            self::StrNers => 'STR Ners',
            self::InstansiLainnya => 'Instansi Lainnya',
        };
    }
}
