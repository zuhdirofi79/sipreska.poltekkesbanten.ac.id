<?php

namespace App\Enums;

enum AttendanceWorkUnit: string
{
    case Direktorat = 'direktorat';
    case Keperawatan = 'keperawatan';
    case Kebidanan = 'kebidanan';
    case TeknologiLaboratoriumMedis = 'teknologi-laboratorium-medis';

    // Legacy values retained for historical attendance responses.
    case D3Keperawatan = 'd3-keperawatan';
    case D3Kebidanan = 'd3-kebidanan';
    case D3Tlm = 'd3-tlm';
    case StrNers = 'str-ners';
    case InstansiLainnya = 'instansi_lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Direktorat => 'Direktorat',
            self::Keperawatan => 'Keperawatan',
            self::Kebidanan => 'Kebidanan',
            self::TeknologiLaboratoriumMedis => 'Teknologi Laboratorium Medis',
            self::D3Keperawatan => 'D3 Keperawatan',
            self::D3Kebidanan => 'D3 Kebidanan',
            self::D3Tlm => 'D3 TLM',
            self::StrNers => 'STR Ners',
            self::InstansiLainnya => 'Instansi Lainnya',
        };
    }

    /**
     * Work unit options shown on the public attendance form.
     *
     * @return list<self>
     */
    public static function publicFormCases(): array
    {
        return [
            self::Direktorat,
            self::Keperawatan,
            self::Kebidanan,
            self::TeknologiLaboratoriumMedis,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function publicFormOptions(): array
    {
        $options = [];

        foreach (self::publicFormCases() as $unit) {
            $options[$unit->value] = $unit->label();
        }

        return $options;
    }
}
