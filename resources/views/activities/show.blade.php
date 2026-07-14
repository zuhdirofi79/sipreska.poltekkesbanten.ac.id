@extends('layouts.app')

@section('title', $activity->title)
@section('page_title', 'Detail Kegiatan')
@section('page_description', $activity->title)

@section('content')
    <x-page-header :title="$activity->title">
        <x-slot:actions>
            <x-status-badge :status="$activity->status" />
            <x-document-completeness-badge :completeness="$completeness" />
            @can('update', $activity)
                <a href="{{ route('activities.edit', $activity) }}" class="sipreska-btn-primary">Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Informasi Kegiatan">
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-gray-500">Tanggal</dt>
                        <dd class="font-medium text-gray-900">{{ $activity->activity_date?->translatedFormat('l, d F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Waktu</dt>
                        <dd class="font-medium text-gray-900">
                            @if ($activity->start_time)
                                {{ substr($activity->start_time, 0, 5) }}
                                @if ($activity->end_time) – {{ substr($activity->end_time, 0, 5) }} @endif
                                WIB
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Lokasi</dt>
                        <dd class="font-medium text-gray-900">{{ $activity->location }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Kategori</dt>
                        <dd class="font-medium text-gray-900">{{ $activity->category?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Unit</dt>
                        <dd class="font-medium text-gray-900">{{ $activity->unit?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">PIC</dt>
                        <dd class="font-medium text-gray-900">
                            {{ $activity->pic_name }}
                            @if ($activity->pic_contact)
                                <span class="block text-xs text-gray-500">{{ $activity->pic_contact }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($activity->description)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-medium text-gray-700">Deskripsi</h4>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $activity->description }}</p>
                    </div>
                @endif

                @if ($activity->objective)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-medium text-gray-700">Tujuan</h4>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $activity->objective }}</p>
                    </div>
                @endif

                @if ($activity->target_participants)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-medium text-gray-700">Target Peserta</h4>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $activity->target_participants }}</p>
                    </div>
                @endif
            </x-card>

            <x-card title="Kelengkapan Dokumen">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['key' => 'invitation', 'label' => 'Surat Undangan', 'done' => $completeness['components']['invitation'] ?? false],
                        ['key' => 'attendance', 'label' => 'Daftar Hadir', 'done' => $completeness['components']['attendance'] ?? false],
                        ['key' => 'minutes', 'label' => 'Notulensi', 'done' => $completeness['components']['minutes'] ?? false],
                        ['key' => 'documentation', 'label' => 'Dokumentasi', 'done' => $completeness['components']['documentation'] ?? false],
                        ['key' => 'publication', 'label' => 'Publikasi', 'done' => $completeness['components']['publication'] ?? false],
                    ] as $item)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <span>{{ $item['label'] }}</span>
                            <x-badge :variant="$item['done'] ? 'green' : 'gray'">{{ $item['done'] ? 'Lengkap' : 'Belum' }}</x-badge>
                        </div>
                    @endforeach
                </div>
                @if (! empty($completeness['missing']))
                    <p class="mt-4 text-sm text-amber-700">Belum lengkap: {{ implode(', ', $completeness['missing']) }}</p>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Metadata">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Dibuat</dt>
                        <dd class="text-gray-900">{{ \App\Support\DateTimeFormatter::display($activity->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Diperbarui</dt>
                        <dd class="text-gray-900">{{ \App\Support\DateTimeFormatter::display($activity->updated_at) }}</dd>
                    </div>
                    @if ($activity->creator)
                        <div>
                            <dt class="text-gray-500">Oleh</dt>
                            <dd class="text-gray-900">{{ $activity->creator->name }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @can('update', $activity)
                <x-card title="Kelola Dokumen">
                    <div class="space-y-2 text-sm">
                        @foreach ([
                            'invitation' => 'Surat Undangan',
                            'attendance' => 'Absensi',
                            'minutes' => 'Notulensi',
                            'documentation' => 'Dokumentasi',
                            'publication' => 'Publikasi',
                        ] as $tab => $label)
                            <a href="{{ route('activities.edit', [$activity, 'tab' => $tab]) }}" class="block rounded-lg px-3 py-2 text-blue-600 hover:bg-blue-50">
                                {{ $label }} &rarr;
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endcan
        </div>
    </div>
@endsection
