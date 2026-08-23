@extends('layouts.app')

@section('title', $activity->title)
@section('page_title', 'Detail Kegiatan Eksternal')
@section('page_description', $activity->title)

@section('content')
    @php
        use App\Support\DateTimeFormatter;
    @endphp

    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('external-activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan Eksternal</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Detail</li>
        </ol>
    </nav>

    <x-page-header :title="$activity->title">
        <x-slot:actions>
            <x-badge :variant="$activity->status->badgeVariant()">{{ $activity->status->label() }}</x-badge>
            @can('update', $activity)
                <a href="{{ route('external-activities.edit', $activity) }}" class="sipreska-btn-primary">Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Informasi Kegiatan">
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Nama Kegiatan</dt>
                        <dd class="font-medium text-gray-900">{{ $activity->title }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Tanggal Kegiatan</dt>
                        <dd class="font-medium text-gray-900">
                            {{ $activity->dates->map(fn ($date) => $date->event_date?->translatedFormat('l, d F Y'))->implode(' · ') ?: '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd><x-badge :variant="$activity->status->badgeVariant()">{{ $activity->status->label() }}</x-badge></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Form Absensi</dt>
                        <dd>
                            @if ($activity->isAcceptingSubmissions())
                                <x-badge variant="green">Aktif</x-badge>
                            @else
                                <x-badge variant="gray">Ditutup</x-badge>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Bagikan Form Absensi">
                <p class="text-sm text-gray-600">
                    Tautan ini bersifat publik. Siapa pun yang memiliki tautan dapat mengisi daftar hadir tanpa login.
                </p>
                <div class="mt-4" x-data="externalShareLink(@js($publicAttendanceUrl))">
                    <label for="external-public-url" class="mb-1 block text-sm font-medium text-gray-700">Tautan Publik Absensi</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            id="external-public-url"
                            type="text"
                            readonly
                            x-ref="urlInput"
                            value="{{ $publicAttendanceUrl }}"
                            class="sipreska-input min-w-0 flex-1 bg-white"
                        >
                        <button type="button" class="sipreska-btn-secondary" @click="copy()">
                            <span x-text="copied ? 'Tersalin' : 'Salin Tautan'"></span>
                        </button>
                    </div>
                </div>

                @can('update', $activity)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($activity->is_open)
                            <form method="POST" action="{{ route('external-activities.close', $activity) }}">
                                @csrf
                                <button type="submit" class="sipreska-btn-secondary">Tutup Form</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('external-activities.open', $activity) }}">
                                @csrf
                                <button type="submit" class="sipreska-btn-primary">Buka Form</button>
                            </form>
                        @endif
                    </div>
                @endcan
            </x-card>

            <x-card title="Ringkasan Absensi">
                <dl class="grid gap-4 sm:grid-cols-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Jumlah Peserta</dt>
                        <dd class="text-xl font-semibold text-gray-900">{{ $stats['total'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Hari Ini</dt>
                        <dd class="text-xl font-semibold text-gray-900">{{ $stats['today'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Submit Terakhir</dt>
                        <dd class="font-medium text-gray-900">{{ DateTimeFormatter::display($stats['last_submitted_at']) }}</dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <a href="{{ route('external-activities.attendance.index', $activity) }}" class="sipreska-btn-primary">Lihat Daftar Hadir</a>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Metadata">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Dibuat</dt>
                        <dd class="text-gray-900">{{ DateTimeFormatter::display($activity->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Diperbarui</dt>
                        <dd class="text-gray-900">{{ DateTimeFormatter::display($activity->updated_at) }}</dd>
                    </div>
                    @if ($activity->creator)
                        <div>
                            <dt class="text-gray-500">Oleh</dt>
                            <dd class="text-gray-900">{{ $activity->creator->name }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @can('delete', $activity)
                <x-card title="Hapus Kegiatan">
                    <p class="text-sm text-gray-600">Penghapusan bersifat lunak. Data absensi tetap tersimpan untuk audit.</p>
                    <form
                        method="POST"
                        action="{{ route('external-activities.destroy', $activity) }}"
                        class="mt-4"
                        x-data
                        @submit.prevent="$dispatch('open-confirm', { id: 'delete-external-activity', form: $el })"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sipreska-btn-secondary text-red-600">Hapus Kegiatan</button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>

    <x-confirm-dialog
        id="delete-external-activity"
        title="Hapus Kegiatan Eksternal"
        message="Apakah Anda yakin ingin menghapus kegiatan ini?"
        confirmLabel="Ya, hapus"
        confirmClass="sipreska-btn-primary bg-red-600 hover:bg-red-700"
    />
@endsection
