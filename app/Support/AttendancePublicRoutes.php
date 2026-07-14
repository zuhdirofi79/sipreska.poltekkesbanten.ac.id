<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

final class AttendancePublicRoutes
{
    public const SHOW = 'attendance.public.show';

    public const STORE = 'attendance.public.store';

    public const SUCCESS = 'attendance.public.success';

    /** @var list<string> */
    public const ALL = [
        self::SHOW,
        self::STORE,
        self::SUCCESS,
    ];

    public static function registered(): bool
    {
        foreach (self::ALL as $name) {
            if (! Route::has($name)) {
                return false;
            }
        }

        return true;
    }

    public static function showUrl(string $publicToken): ?string
    {
        if ($publicToken === '' || ! Route::has(self::SHOW)) {
            return null;
        }

        return route(self::SHOW, ['public_token' => $publicToken]);
    }
}
