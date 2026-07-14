<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Logo asset lives in public/images/ and is served via asset() helper.
    |
    */
    'logo_path' => 'images/logo-poltekkes-banten.png',

    'attendance_letterhead_path' => 'images/daftar-hadir-header.jpeg',

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
    */
    'display_timezone' => 'Asia/Jakarta',

    'initial_admin_password_env' => 'SIPRESKA_INITIAL_ADMIN_PASSWORD',

    /*
    |--------------------------------------------------------------------------
    | Activity Module
    |--------------------------------------------------------------------------
    */
    'publication_optional' => env('SIPRESKA_PUBLICATION_OPTIONAL', true),

    'upload_max_kb' => (int) env('SIPRESKA_UPLOAD_MAX_KB', 5120),

    'attendance_poll_seconds' => (int) env('SIPRESKA_ATTENDANCE_POLL_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Document Storage (Hostinger-compatible, no storage:link)
    |--------------------------------------------------------------------------
    |
    | Files are stored under public/docs/{type}/{activity_id}/ with random
    | filenames. Database stores relative paths from the public root, e.g.:
    | docs/surat_undangan/15/{uuid}.pdf
    |
    | Production example:
    | SIPRESKA_DOCUMENT_ROOT=/home/u708283844/domains/sipreska.poltekkesbanten.ac.id/sipreska-app/public/docs
    |
    */
    'document_disk' => env('SIPRESKA_DOCUMENT_DISK', 'sipreska'),

    'document_root' => env('SIPRESKA_DOCUMENT_ROOT', public_path('docs')),

    'document_path_prefix' => 'docs',

    'document_folders' => [
        'invitation' => 'surat_undangan',
        'minutes' => 'notulensi',
        'documentation' => 'dokumentasi',
        'attendance' => 'daftar_hadir',
        'publication' => 'publikasi',
        'signature' => 'tanda_tangan',
    ],

    'blocked_extensions' => [
        'php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'htaccess', 'exe', 'bat', 'cmd', 'com', 'js', 'html', 'htm',
    ],

    'allowed_mimes' => [
        'invitation' => ['application/pdf'],
        'minutes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'documentation' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'signature' => ['image/png'],
    ],

    'allowed_extensions' => [
        'invitation' => ['pdf'],
        'minutes' => ['pdf', 'doc', 'docx'],
        'documentation' => ['jpg', 'jpeg', 'png', 'webp'],
        'signature' => ['png'],
    ],

];
