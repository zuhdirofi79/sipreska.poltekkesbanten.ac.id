<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir Eksternal - {{ $activity->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; padding: 0; }
        h1 { font-size: 16px; margin: 0 0 8px; text-align: center; text-transform: uppercase; letter-spacing: 0.04em; }
        .meta { margin-bottom: 16px; color: #444; font-size: 11px; }
        .meta div { margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; vertical-align: middle; }
        th { background: #f3f4f6; }
        .footer { margin-top: 12px; font-size: 10px; color: #666; }
        .signature-cell { width: 90px; text-align: center; }
        .signature-img { height: 32px; max-width: 100px; width: auto; display: block; margin: 0 auto; }
    </style>
</head>
<body>
    <x-attendance-letterhead context="pdf" />

    <h1>Daftar Hadir Kegiatan Eksternal</h1>

    <div class="meta">
        <div><strong>Nama Kegiatan:</strong> {{ $activity->title }}</div>
        <div>
            <strong>Tanggal Kegiatan:</strong>
            {{ $activity->dates->map(fn ($date) => $date->event_date?->locale('id')->translatedFormat('d F Y'))->implode(', ') }}
        </div>
        <div><strong>Status:</strong> {{ $activity->status->label() }}</div>
        @if ($activity->hasOfflineLocation())
            <div><strong>Lokasi Offline:</strong> {{ $activity->location_address }}</div>
        @endif
        @if ($activity->hasOnlineUrl())
            <div><strong>Link Online:</strong> {{ $activity->online_url }}</div>
        @endif
        <div><strong>Dicetak:</strong> {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Full Name</th>
                <th>Institution</th>
                <th>Email Address</th>
                <th>Attendance Date</th>
                <th>Waktu Submit</th>
                <th class="signature-cell">TTD</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($participants as $index => $participant)
                @php $signatureSrc = \App\Support\ExternalAttendanceSignatureExport::pdfSrc($participant); @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $participant->full_name }}</td>
                    <td>{{ $participant->institution }}</td>
                    <td>{{ $participant->email }}</td>
                    <td>{{ $participant->formattedAttendanceDates('d M Y') }}</td>
                    <td>{{ \App\Support\DateTimeFormatter::display($participant->submitted_at) }}</td>
                    <td class="signature-cell">
                        @if ($signatureSrc)
                            <img src="{{ $signatureSrc }}" class="signature-img" alt="TTD">
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dokumen ini dihasilkan otomatis oleh SIPRESKA.</div>
</body>
</html>
