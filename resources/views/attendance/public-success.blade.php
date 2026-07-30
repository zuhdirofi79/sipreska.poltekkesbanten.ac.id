@extends('layouts.public-attendance')

@section('title', 'Absensi Berhasil Dikirim')

@section('content')
    @php
        $timezone = config('sipreska.display_timezone');
        $submittedAt = ! empty($successData['submitted_at'])
            ? \Illuminate\Support\Carbon::parse($successData['submitted_at'])->timezone($timezone)
            : null;
        $activityDate = ! empty($successData['activity_date'])
            ? \Illuminate\Support\Carbon::parse($successData['activity_date'])->timezone($timezone)
            : $activity->activity_date?->timezone($timezone);
    @endphp

    <x-attendance-letterhead />

    <main class="public-attendance-form-wrapper">
        <div class="sipreska-card p-6 lg:p-8">
            @if (is_array($successData) && ! empty($successData['name']))
                <div class="text-center">
                    <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>

                    <h1 class="text-2xl font-bold text-gray-900">Absensi Berhasil Dikirim</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Terima kasih. Data kehadiran Anda telah tercatat dalam sistem.
                    </p>
                    <p class="mt-1 text-sm text-gray-500">Anda tidak perlu mengisi form kembali.</p>
                </div>

                <dl class="mt-8 space-y-4 border-t border-gray-100 pt-6 text-sm">
                    <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                        <dt class="font-medium text-gray-500">Nama</dt>
                        <dd class="font-semibold text-gray-900">{{ $successData['name'] }}</dd>
                    </div>

                    @if (! empty($successData['nip_masked']))
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                            <dt class="font-medium text-gray-500">NIP</dt>
                            <dd class="font-mono text-gray-900">{{ $successData['nip_masked'] }}</dd>
                        </div>
                    @endif

                    <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                        <dt class="font-medium text-gray-500">Kegiatan</dt>
                        <dd class="text-right font-semibold text-gray-900">{{ $successData['activity_title'] ?? $activity->title }}</dd>
                    </div>

                    <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                        <dt class="font-medium text-gray-500">Tanggal Kegiatan</dt>
                        <dd class="text-gray-900">{{ $activityDate?->translatedFormat('l, d F Y') }}</dd>
                    </div>

                    <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                        <dt class="font-medium text-gray-500">Tempat Kegiatan</dt>
                        <dd class="text-right text-gray-900">{{ $successData['location'] ?? $activity->location }}</dd>
                    </div>

                    <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                        <dt class="font-medium text-gray-500">Waktu Tercatat</dt>
                        <dd class="text-gray-900">
                            {{ $submittedAt?->translatedFormat('d F Y, H:i:s') }} WIB
                        </dd>
                    </div>

                    <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                        <dt class="font-medium text-gray-500">Status</dt>
                        <dd>
                            <span class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">Tercatat</span>
                        </dd>
                    </div>
                </dl>

                @if ($submittedAt)
                    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-700">
                        <div class="font-semibold text-gray-900">Bukti Kehadiran</div>
                        <p class="mt-2">
                            Data Anda telah tersimpan pada sistem pada:
                            <strong>{{ $submittedAt->translatedFormat('d F Y, H:i:s') }} WIB</strong>
                        </p>
                        @if (! empty($successData['reference_code']))
                            <p class="mt-2 text-xs text-gray-500">
                                Kode referensi: <span class="font-mono font-semibold text-gray-700">{{ $successData['reference_code'] }}</span>
                            </p>
                        @endif
                    </div>
                @endif

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <button
                        type="button"
                        class="sipreska-btn-primary"
                        onclick="window.close(); setTimeout(function () { document.getElementById('close-fallback').classList.remove('hidden'); }, 300);"
                    >
                        Tutup Halaman
                    </button>
                    <a href="{{ url('/') }}" class="sipreska-btn-secondary text-center">Kembali ke Halaman Utama SIPRESKA</a>
                </div>

                <p id="close-fallback" class="mt-4 hidden text-center text-xs text-gray-500">
                    Browser tidak mengizinkan penutupan tab. Anda dapat menutup halaman ini secara manual.
                </p>
            @else
                <div class="text-center">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                        </svg>
                    </div>

                    <h1 class="text-xl font-bold text-gray-900">Konfirmasi Tidak Tersedia</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Tidak ada data pengiriman terbaru yang dapat ditampilkan.
                    </p>

                    <p class="mt-4 text-sm font-semibold text-gray-900">{{ $activity->title }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $activity->activity_date?->timezone($timezone)->translatedFormat('l, d F Y') }}
                        &middot; {{ $activity->location }}
                    </p>

                    <button
                        type="button"
                        class="sipreska-btn-primary mt-6"
                        onclick="window.close(); setTimeout(function () { document.getElementById('neutral-close-fallback').classList.remove('hidden'); }, 300);"
                    >
                        Tutup Halaman
                    </button>

                    <p id="neutral-close-fallback" class="mt-4 hidden text-center text-xs text-gray-500">
                        Browser tidak mengizinkan penutupan tab. Anda dapat menutup halaman ini secara manual.
                    </p>
                </div>
            @endif
        </div>
    </main>
@endsection
