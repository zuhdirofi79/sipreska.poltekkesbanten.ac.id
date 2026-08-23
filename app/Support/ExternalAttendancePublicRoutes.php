<?php

namespace App\Support;

use App\Models\ExternalActivity;
use Illuminate\Support\Facades\Route;

final class ExternalAttendancePublicRoutes
{
    public const SHOW = 'attendance.external.show';

    public const STORE = 'attendance.external.store';

    public const SUCCESS = 'attendance.external.success';

    public const SHORT_SHOW = 'attendance.external.short.show';

    public const SHORT_STORE = 'attendance.external.short.store';

    public static function showUrlForActivity(?ExternalActivity $activity): ?string
    {
        if ($activity === null) {
            return null;
        }

        if (is_string($activity->short_code) && $activity->short_code !== '' && Route::has(self::SHORT_SHOW)) {
            return route(self::SHORT_SHOW, ['short_code' => $activity->short_code]);
        }

        if ($activity->public_token === '' || ! Route::has(self::SHOW)) {
            return null;
        }

        return route(self::SHOW, ['public_token' => $activity->public_token]);
    }

    public static function storeUrlForActivity(ExternalActivity $activity): string
    {
        if (is_string($activity->short_code) && $activity->short_code !== '' && Route::has(self::SHORT_STORE)) {
            return route(self::SHORT_STORE, ['short_code' => $activity->short_code]);
        }

        return route(self::STORE, ['public_token' => $activity->public_token]);
    }
}
