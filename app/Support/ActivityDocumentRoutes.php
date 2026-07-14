<?php

namespace App\Support;

final class ActivityDocumentRoutes
{
    /** @var list<string> Named routes referenced by activity document Blade partials. */
    public const BLADE_ROUTES = [
        'activities.surat-undangan.store',
        'activities.surat-undangan.view',
        'activities.surat-undangan.download',
        'activities.surat-undangan.destroy',
        'activities.notulensi.store',
        'activities.notulensi.view',
        'activities.notulensi.download',
        'activities.notulensi.destroy',
        'activities.dokumentasi.store',
        'activities.dokumentasi.update',
        'activities.dokumentasi.view',
        'activities.dokumentasi.download',
        'activities.dokumentasi.thumbnail',
        'activities.dokumentasi.destroy',
        'activities.publikasi.store',
        'activities.publikasi.update',
        'activities.publikasi.destroy',
        'activities.daftar-hadir.index',
        'activities.daftar-hadir.settings',
        'activities.daftar-hadir.store',
        'activities.daftar-hadir.responses',
        'activities.daftar-hadir.responses.data',
        'activities.daftar-hadir.response.show',
        'activities.daftar-hadir.poll',
        'activities.daftar-hadir.qr',
        'activities.daftar-hadir.export-excel',
        'activities.daftar-hadir.export-pdf',
        'activities.daftar-hadir.print',
        'activities.daftar-hadir.open',
        'activities.daftar-hadir.close',
        'activities.daftar-hadir.response.edit',
        'activities.daftar-hadir.response.update',
        'activities.daftar-hadir.response.destroy',
    ];
}
