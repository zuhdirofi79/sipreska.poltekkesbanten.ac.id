@extends('layouts.app')

@section('title', 'Agenda Kegiatan Eksternal')
@section('page_title', 'Agenda Kegiatan Eksternal')
@section('page_description', 'Kelola kegiatan eksternal, tanggal kehadiran, dan daftar hadir publik.')

@section('content')
    <x-page-header title="Daftar Kegiatan Eksternal">
        <x-slot:actions>
            @can('create', App\Models\ExternalActivity::class)
                <a href="{{ route('external-activities.create') }}" class="sipreska-btn-primary">+ Tambah Kegiatan Eksternal</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 sipreska-card p-4">
        <form method="GET" class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama kegiatan..." class="sipreska-input lg:col-span-2">
            <select name="status" class="sipreska-select">
                <option value="">Semua Status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="sipreska-btn-secondary w-full">Filter</button>
                <a href="{{ route('external-activities.index') }}" class="sipreska-btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <x-data-table>
        <x-slot:head>
            <tr>
                <x-data-table-th>Nama Kegiatan</x-data-table-th>
                <x-data-table-th>Tanggal</x-data-table-th>
                <x-data-table-th>Status</x-data-table-th>
                <x-data-table-th>Peserta</x-data-table-th>
                <x-data-table-th>Form</x-data-table-th>
                <x-data-table-th align="right">Aksi</x-data-table-th>
            </tr>
        </x-slot:head>

        @forelse ($activities as $activity)
            <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                <td class="px-4 py-3">
                    <a href="{{ route('external-activities.show', $activity) }}" class="font-medium text-gray-900 hover:text-blue-600">
                        {{ $activity->title }}
                    </a>
                </td>
                <td class="px-4 py-3">
                    {{ $activity->dates->map(fn ($date) => $date->event_date?->translatedFormat('d M Y'))->implode(', ') ?: '-' }}
                </td>
                <td class="px-4 py-3">
                    <x-badge :variant="$activity->status->badgeVariant()">{{ $activity->status->label() }}</x-badge>
                </td>
                <td class="px-4 py-3">{{ $activity->participants_count }}</td>
                <td class="px-4 py-3">
                    @if ($activity->isAcceptingSubmissions())
                        <x-badge variant="green">Aktif</x-badge>
                    @else
                        <x-badge variant="gray">Ditutup</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="{{ route('external-activities.show', $activity) }}" class="text-blue-600 hover:underline">Detail</a>
                    @can('update', $activity)
                        <span class="mx-1 text-gray-300">|</span>
                        <a href="{{ route('external-activities.edit', $activity) }}" class="text-blue-600 hover:underline">Edit</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-empty-state title="Belum ada kegiatan eksternal" description="Mulai dengan menambahkan kegiatan eksternal baru.">
                        <x-slot:action>
                            @can('create', App\Models\ExternalActivity::class)
                                <a href="{{ route('external-activities.create') }}" class="sipreska-btn-primary">+ Tambah Kegiatan Eksternal</a>
                            @endcan
                        </x-slot:action>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($activities->hasPages())
        <div class="mt-4">{{ $activities->links() }}</div>
    @endif
@endsection
