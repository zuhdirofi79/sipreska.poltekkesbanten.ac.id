<?php

namespace App\Support;

use App\Models\AttendanceForm;
use Illuminate\Support\Facades\Route;

final class AttendancePublicRoutes
{
    public const SHOW = 'attendance.public.show';

    public const STORE = 'attendance.public.store';

    public const SUCCESS = 'attendance.public.success';

    public const SHORT_SHOW = 'attendance.short.show';

    public const SHORT_STORE = 'attendance.short.store';

    /** @var list<string> */
    public const ALL = [
        self::SHOW,
        self::STORE,
        self::SUCCESS,
    ];

    /** @var list<string> */
    public const SHORT_ALL = [
        self::SHORT_SHOW,
        self::SHORT_STORE,
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

    public static function showUrlForForm(?AttendanceForm $form): ?string
    {
        if ($form === null) {
            return null;
        }

        if (is_string($form->short_code) && $form->short_code !== '' && Route::has(self::SHORT_SHOW)) {
            return route(self::SHORT_SHOW, ['short_code' => $form->short_code]);
        }

        return self::showUrl((string) $form->public_token);
    }

    public static function storeUrlForForm(AttendanceForm $form): string
    {
        if (is_string($form->short_code) && $form->short_code !== '' && Route::has(self::SHORT_STORE)) {
            return route(self::SHORT_STORE, ['short_code' => $form->short_code]);
        }

        return route(self::STORE, ['public_token' => $form->public_token]);
    }
}
