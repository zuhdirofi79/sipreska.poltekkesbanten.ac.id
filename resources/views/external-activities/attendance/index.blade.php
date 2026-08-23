@extends('layouts.app')

@section('title', 'Daftar Hadir Eksternal')
@section('page_title', 'Daftar Hadir Eksternal')
@section('page_description', $activity->title)

@section('content')
    @php
        use App\Support\DateTimeFormatter;
        $exportSuffix = $exportQuery !== '' ? '?' . $exportQuery : '';
    @endphp

    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('external-activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan Eksternal</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('external-activities.show', $activity) }}" class="hover:text-blue-600">Detail</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Daftar Hadir</li>
        </ol>
    </nav>

    <x-page-header title="Daftar Hadir Peserta">
        <x-slot:actions>
            <a href="{{ route('external-activities.show', $activity) }}" class="sipreska-btn-secondary">Kembali</a>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Ringkasan Kegiatan" class="mb-6">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <dt class="text-gray-500">Nama Kegiatan</dt>
                <dd class="font-medium text-gray-900">{{ $activity->title }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Tanggal Kegiatan</dt>
                <dd class="font-medium text-gray-900">
                    {{ $activity->dates->map(fn ($date) => $date->event_date?->translatedFormat('d F Y'))->implode(', ') }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Total Peserta</dt>
                <dd class="font-medium text-gray-900">{{ $stats['total'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Submit Terakhir</dt>
                <dd class="font-medium text-gray-900">{{ DateTimeFormatter::display($stats['last_submitted_at']) }}</dd>
            </div>
            @if ($activity->hasOfflineLocation())
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Lokasi Offline</dt>
                    <dd class="font-medium text-gray-900">{{ $activity->location_address }}</dd>
                </div>
            @endif
            @if ($activity->hasOnlineUrl())
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Kegiatan Online</dt>
                    <dd>
                        <a href="{{ $activity->online_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:underline">Buka Link Meeting</a>
                    </dd>
                </div>
            @endif
        </dl>
    </x-card>

    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <a href="{{ route('external-activities.attendance.export-excel', $activity) }}{{ $exportSuffix }}" class="sipreska-btn-secondary w-full sm:w-auto">Export Excel</a>
        <a href="{{ route('external-activities.attendance.export-pdf', $activity) }}{{ $exportSuffix }}" class="sipreska-btn-secondary w-full sm:w-auto">Export PDF</a>
    </div>

    <div class="mb-4 sipreska-card p-4">
        <form method="GET" action="{{ route('external-activities.attendance.index', $activity) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
            <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama, institusi, email..." class="sipreska-input lg:col-span-2">
            <input type="text" name="institution" value="{{ $filters['institution'] }}" placeholder="Filter institusi" class="sipreska-input">
            <input type="text" name="email" value="{{ $filters['email'] }}" placeholder="Filter email" class="sipreska-input">
            <select name="attendance_date" class="sipreska-select">
                <option value="">Semua tanggal hadir</option>
                @foreach ($activity->dates as $date)
                    <option value="{{ $date->event_date?->format('Y-m-d') }}" @selected($filters['attendance_date'] === $date->event_date?->format('Y-m-d'))>
                        {{ $date->event_date?->translatedFormat('d F Y') }}
                    </option>
                @endforeach
            </select>
            <select name="sort" class="sipreska-select">
                <option value="oldest" @selected($filters['sort'] === 'oldest')>Terlama</option>
                <option value="newest" @selected($filters['sort'] === 'newest')>Terbaru</option>
                <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Nama A–Z</option>
                <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Nama Z–A</option>
            </select>
            <div class="flex gap-2 lg:col-span-6">
                <button type="submit" class="sipreska-btn-primary w-full sm:w-auto">Tampilkan</button>
                <a href="{{ route('external-activities.attendance.index', $activity) }}" class="sipreska-btn-secondary w-full sm:w-auto text-center">Reset</a>
            </div>
        </form>
    </div>

    @if ($participants->isEmpty())
        <x-card>
            <x-empty-state
                title="Belum ada peserta yang mengisi form absensi."
                description="Bagikan tautan form absensi kepada peserta eksternal."
            />
        </x-card>
    @else
        <div class="sipreska-card overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">No</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Full Name</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Institution</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Email Address</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Attendance Date</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">TTD</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Waktu Submit</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($participants as $index => $participant)
                        @php
                            $signatureUrl = $participant->signature_path
                                ? route('external-activities.files.signature', [$activity, $participant, 'inline' => 1])
                                : null;
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $participants->firstItem() + $index }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $participant->full_name }}</td>
                            <td class="px-4 py-3">{{ $participant->institution }}</td>
                            <td class="px-4 py-3">{{ $participant->email }}</td>
                            <td class="px-4 py-3">{{ $participant->formattedAttendanceDates('d M Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($signatureUrl)
                                    <button
                                        type="button"
                                        class="inline-block rounded border border-gray-200 bg-white p-0.5 hover:border-blue-300"
                                        @click="$dispatch('open-signature-preview', { url: @js($signatureUrl), name: @js($participant->full_name) })"
                                        aria-label="Pratinjau tanda tangan {{ $participant->full_name }}"
                                    >
                                        <img src="{{ $signatureUrl }}" alt="Tanda tangan {{ $participant->full_name }}" class="h-10 w-16 object-contain" loading="lazy">
                                    </button>
                                @else
                                    <span class="text-gray-400">Tidak tersedia</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ DateTimeFormatter::display($participant->submitted_at) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('external-activities.attendance.show', [$activity, $participant]) }}" class="text-blue-600 hover:underline">Detail</a>
                                <span class="mx-1 text-gray-300">|</span>
                                <a href="{{ route('external-activities.attendance.edit', [$activity, $participant]) }}" class="text-blue-600 hover:underline">Koreksi</a>
                                <span class="mx-1 text-gray-300">|</span>
                                <form
                                    method="POST"
                                    action="{{ route('external-activities.attendance.destroy', [$activity, $participant]) }}"
                                    class="inline"
                                    x-data
                                    @submit.prevent="$dispatch('open-confirm', { id: 'delete-external-participant-{{ $participant->id }}', form: $el })"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $participants->links() }}
        </div>
    @endif

    <div
        x-data="{ open: false, url: '', name: '' }"
        x-on:open-signature-preview.window="open = true; url = $event.detail.url; name = $event.detail.name"
        x-cloak
    >
        <div
            x-show="open"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @keydown.escape.window="open = false"
        >
            <div
                x-show="open"
                x-transition
                @click.outside="open = false"
                class="sipreska-card w-full max-w-lg p-6"
                role="dialog"
                aria-modal="true"
            >
                <h3 class="text-base font-semibold text-gray-900" x-text="'Tanda Tangan — ' + name"></h3>
                <div class="mt-4 flex justify-center rounded-lg border border-gray-100 bg-white p-4">
                    <img :src="url" alt="Pratinjau tanda tangan" class="max-h-64 max-w-full object-contain">
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" class="sipreska-btn-primary" @click="open = false">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    @foreach ($participants as $participant)
        <x-confirm-dialog
            :id="'delete-external-participant-' . $participant->id"
            title="Hapus Data Peserta"
            message="Apakah Anda yakin ingin menghapus data peserta {{ $participant->full_name }}? Tindakan ini tidak dapat dibatalkan."
            confirmLabel="Ya, hapus"
            confirmClass="sipreska-btn-primary bg-red-600 hover:bg-red-700"
        />
    @endforeach
@endsection
