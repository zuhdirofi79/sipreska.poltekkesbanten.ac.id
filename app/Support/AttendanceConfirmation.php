<?php

namespace App\Support;

use App\Models\AttendanceResponse;

class AttendanceConfirmation
{
    public static function token(AttendanceResponse $response): string
    {
        return hash_hmac(
            'sha256',
            $response->id.'|'.$response->attendance_form_id,
            (string) config('app.key'),
        );
    }

    public static function referenceCode(AttendanceResponse $response): string
    {
        $hash = hash(
            'sha256',
            $response->id.'|'.$response->submitted_at?->timestamp.'|'.config('app.key'),
        );

        return strtoupper(substr($hash, 0, 8));
    }

    public static function maskIdentity(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '—';
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }

    /** @deprecated Use maskIdentity() instead. Kept for backward compatibility. */
    public static function maskNipNik(string $nipNik): string
    {
        return self::maskIdentity($nipNik);
    }
}
