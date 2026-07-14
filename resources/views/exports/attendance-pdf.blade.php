<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir - {{ $activity->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 16px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .footer { margin-top: 12px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>Daftar Hadir</h1>
    <div class="meta">
        <div><strong>Kegiatan:</strong> {{ $activity->title }}</div>
        <div><strong>Tanggal:</strong> {{ $activity->activity_date?->locale('id')->translatedFormat('d F Y') }}</div>
        <div><strong>Lokasi:</strong> {{ $activity->location }}</div>
        <div><strong>Unit:</strong> {{ $activity->unit?->short_name }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>NIP/NIK</th>
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
                    <td>{{ $response->nip_nik }}</td>
                    <td>{{ $response->position ?? '-' }}</td>
                    <td>{{ \App\Enums\AttendanceWorkUnit::tryFrom($response->work_unit)?->label() ?? $response->work_unit }}</td>
                    <td>{{ $response->institution_name ?? '-' }}</td>
                    <td>{{ \App\Support\DateTimeFormatter::display($response->submitted_at) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Belum ada peserta.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Dicetak: {{ $generatedAt }}</div>
</body>
</html>
