<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy Filament Emergency Access
    |--------------------------------------------------------------------------
    |
    | Temporary rollback access for Filament /admin only. Do NOT use this
    | email for custom SIPRESKA route authorization.
    |
    */
    'legacy_filament_email' => env(
        'SIPRESKA_LEGACY_FILAMENT_EMAIL',
        'auloraqowiyboyqowboy@gmail.com'
    ),

    /*
    |--------------------------------------------------------------------------
    | Timestamp Strategy
    |--------------------------------------------------------------------------
    |
    | - APP_TIMEZONE=Asia/Jakarta drives Carbon::now() and Eloquent casting.
    | - Server creates all attendance timestamps (never trust browser time).
    | - Display/export format uses WIB suffix via Sipreska\Support\DateTimeFormatter.
    | - Do not convert the same instant twice across layers.
    |
    */
    'display_timezone' => 'Asia/Jakarta',

    'initial_admin_password_env' => 'SIPRESKA_INITIAL_ADMIN_PASSWORD',

];
