<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir - {{ $activity->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 0; }
        h1 { font-size: 16px; margin: 0 0 8px; text-align: center; text-transform: uppercase; letter-spacing: 0.04em; }
        .meta { margin-bottom: 16px; color: #444; }
        .meta div { margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .footer { margin-top: 12px; font-size: 10px; color: #666; }
        .legacy { font-size: 9px; color: #92400e; }
        .public-attendance-letterhead-wrapper {
            width: 100%;
            max-width: none;
            margin: 0 0 10mm;
            padding: 0;
        }
        .attendance-letterhead {
            display: block;
            width: 100%;
            max-width: 100%;
            height: auto;
            margin: 0 auto 10mm;
            object-fit: contain;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .attendance-letterhead-fallback {
            width: 100%;
            padding: 16px 12px;
            text-align: center;
            border: 1px solid #ccc;
            margin-bottom: 10mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .attendance-letterhead-fallback-line {
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.08em;
            line-height: 1.5;
        }
        @media print {
            .attendance-letterhead {
                display: block;
                width: 100%;
                max-width: 100%;
                height: auto;
                margin: 0 auto 10mm;
                object-fit: contain;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .public-attendance-letterhead-wrapper {
                width: 100%;
                max-width: none;
                margin: 0 0 10mm;
                padding: 0;
            }
        }
    </style>
    @isset($printMode)
        <script>window.onload = function () { window.print(); };</script>
    @endisset
</head>
<body>
    <x-attendance-letterhead context="pdf" />

    <h1>Daftar Hadir Kegiatan</h1>

    <div class="meta">
        <div><strong>Judul Kegiatan:</strong> {{ $activity->title }}</div>
        <div><strong>Hari/Tanggal:</strong> {{ $activity->activity_date?->locale('id')->translatedFormat('l, d F Y') }}</div>
        @if ($activity->start_time)
            <div><strong>Waktu:</strong> {{ substr($activity->start_time, 0, 5) }}@if ($activity->end_time) – {{ substr($activity->end_time, 0, 5) }}@endif WIB</div>
        @endif
        <div><strong>Tempat:</strong> {{ $activity->location }}</div>
        <div><strong>Unit Penyelenggara:</strong> {{ $activity->unit?->short_name ?? '-' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>NIK</th>
                <th>NIP</th>
                <th>Identitas Lama</th>
                <th>Jabatan</th>
                <th>Unit Kerja</th>
                <th>Instansi</th>
                <th>Waktu Submit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($responses as $index => $response)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $response->name }}</td>
                    <td>{{ $response->nik ?? '-' }}</td>
                    <td>{{ $response->nip ?? '-' }}</td>
                    <td>
                        @if ($response->hasLegacyIdentity())
                            {{ $response->legacyIdentity() }}
                            <div class="legacy">Data legacy</div>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $response->position ?? '-' }}</td>
                    <td>{{ \App\Enums\AttendanceWorkUnit::tryFrom($response->work_unit)?->label() ?? $response->work_unit }}</td>
                    <td>{{ $response->institution_name ?? '-' }}</td>
                    <td>{{ \App\Support\DateTimeFormatter::display($response->submitted_at) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Belum ada peserta.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Dicetak: {{ $generatedAt }}</div>
</body>
</html>
