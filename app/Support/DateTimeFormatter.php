<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DateTimeFormatter
{
    public static function display(?CarbonInterface $dateTime): string
    {
        if ($dateTime === null) {
            return '-';
        }

        return $dateTime
            ->timezone(config('sipreska.display_timezone', 'Asia/Jakarta'))
            ->locale('id')
            ->translatedFormat('d F Y H:i:s').' WIB';
    }
}
