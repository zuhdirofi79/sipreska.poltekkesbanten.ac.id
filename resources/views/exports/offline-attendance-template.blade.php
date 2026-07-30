<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Template Daftar Hadir - {{ $activity->title }}</title>
    <style>
        @page { margin: 12mm 12mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; padding: 0; }
        h1 { font-size: 15px; margin: 0 0 8px; text-align: center; text-transform: uppercase; letter-spacing: 0.05em; }
        .meta { margin: 0 0 8px; color: #333; font-size: 10px; }
        .meta div { margin-bottom: 2px; }
        .hint {
            margin: 0 0 10px;
            padding: 6px 8px;
            border: 1px solid #fbbf24;
            background: #fffbeb;
            color: #92400e;
            font-size: 9px;
        }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #777; padding: 4px 5px; text-align: left; vertical-align: middle; }
        th { background: #f3f4f6; font-size: 9px; text-align: center; }
        td { height: 28px; }
        .col-no { width: 28px; text-align: center; }
        .col-name { width: 32%; }
        .col-nip { width: 18%; }
        .col-position { width: 18%; }
        .col-sign { width: 22%; }
        .footer {
            margin-top: 10px;
            font-size: 8px;
            color: #555;
            width: 100%;
        }
        .footer-left { float: left; width: 70%; }
        .footer-right { float: right; width: 28%; text-align: right; }
        .qr { width: 56px; height: 56px; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 8px; }
        .attendance-letterhead {
            display: block;
            width: 100%;
            max-width: 100%;
            height: auto;
            margin: 0 auto 8mm;
        }
        .attendance-letterhead-fallback {
            width: 100%;
            padding: 12px;
            text-align: center;
            border: 1px solid #ccc;
            margin-bottom: 8mm;
            font-weight: 700;
            letter-spacing: 0.06em;
        }
    </style>
</head>
<body>
    @if (! empty($letterheadSrc))
        <img src="{{ $letterheadSrc }}" alt="Kop surat" class="attendance-letterhead">
    @else
        <div class="attendance-letterhead-fallback">
            KEMENTERIAN KESEHATAN<br>
            POLITEKNIK KESEHATAN BANTEN
        </div>
    @endif

    <h1>Daftar Hadir Kegiatan</h1>

    <div class="meta">
        <div><strong>Nama Kegiatan:</strong> {{ $activity->title }}</div>
        <div><strong>Hari/Tanggal:</strong> {{ $activity->activity_date?->locale('id')->translatedFormat('l, d F Y') ?? '-' }}</div>
        @if ($activity->start_time || $activity->end_time)
            <div>
                <strong>Waktu:</strong>
                {{ $activity->start_time ? substr((string) $activity->start_time, 0, 5) : '--:--' }}
                @if ($activity->end_time)
                    – {{ substr((string) $activity->end_time, 0, 5) }}
                @endif
                WIB
            </div>
        @endif
        <div><strong>Tempat:</strong> {{ $activity->location ?? '-' }}</div>
        <div><strong>Unit Penyelenggara:</strong> {{ $activity->unit?->short_name ?? $activity->unit?->name ?? '-' }}</div>
    </div>

    <div class="hint">
        Peserta wajib mengisi NIP. Jika tidak memiliki NIP, isi dengan 0 atau -.
        Gunakan template resmi SIPRESKA ini agar hasil scan mudah diimport kembali.
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-name">Nama Lengkap</th>
                <th class="col-nip">NIP</th>
                <th class="col-position">Jabatan</th>
                <th class="col-sign">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 1; $i <= $rows; $i++)
                <tr>
                    <td class="col-no">{{ $i }}</td>
                    <td class="col-name">&nbsp;</td>
                    <td class="col-nip">&nbsp;</td>
                    <td class="col-position">&nbsp;</td>
                    <td class="col-sign">&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">
            Template resmi SIPRESKA · Dicetak: {{ $generatedAt }}<br>
            Kode template: <span class="code">{{ $templateCode }}</span>
        </div>
        <div class="footer-right">
            @if (! empty($qrSrc))
                <img src="{{ $qrSrc }}" alt="QR kegiatan" class="qr">
            @endif
        </div>
    </div>
</body>
</html>
