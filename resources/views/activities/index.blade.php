@extends('layouts.app')

@section('title', 'Agenda Kegiatan')
@section('page_title', 'Agenda Kegiatan')
@section('page_description', 'Kelola daftar kegiatan, status, dan kelengkapan dokumen.')

@section('content')
    <x-page-header title="Daftar Kegiatan">
        <x-slot:actions>
            @can('create', App\Models\Activity::class)
                <a href="{{ route('activities.create') }}" class="sipreska-btn-primary">+ Tambah Kegiatan</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 sipreska-card p-4">
        <form method="GET" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari judul kegiatan..." class="sipreska-input lg:col-span-2">
            <select name="category_id" class="sipreska-select">
                <option value="">Semua Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="unit_id" class="sipreska-select">
                <option value="">Semua Unit</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((string) request('unit_id') === (string) $unit->id)>{{ $unit->short_name }}</option>
                @endforeach
            </select>
            <select name="status" class="sipreska-select">
                <option value="">Semua Status</option>
                @foreach (\App\Enums\ActivityStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="sipreska-btn-secondary w-full">Filter</button>
                <a href="{{ route('activities.index') }}" class="sipreska-btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <x-data-table>
        <x-slot:head>
            <tr>
                <x-data-table-th>Judul</x-data-table-th>
                <x-data-table-th>Tanggal</x-data-table-th>
                <x-data-table-th>Kategori</x-data-table-th>
                <x-data-table-th>Unit</x-data-table-th>
                <x-data-table-th>Status</x-data-table-th>
                <x-data-table-th>Kelengkapan</x-data-table-th>
                <x-data-table-th align="right">Aksi</x-data-table-th>
            </tr>
        </x-slot:head>

        @forelse ($activities as $activity)
            @php $completeness = $completenessMap[$activity->id] ?? ['completed' => 0, 'total' => 4, 'label' => '-', 'missing' => []]; @endphp
            <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                <td class="px-4 py-3">
                    <a href="{{ route('activities.show', $activity) }}" class="font-medium text-gray-900 hover:text-blue-600">
                        {{ $activity->title }}
                    </a>
                    <div class="text-xs text-gray-500">{{ $activity->location }}</div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    {{ $activity->activity_date?->translatedFormat('d M Y') }}
                    @if ($activity->start_time)
                        <div class="text-xs text-gray-500">{{ substr($activity->start_time, 0, 5) }} WIB</div>
                    @endif
                </td>
                <td class="px-4 py-3">{{ $activity->category?->name ?? '-' }}</td>
                <td class="px-4 py-3">{{ $activity->unit?->short_name ?? '-' }}</td>
                <td class="px-4 py-3"><x-status-badge :status="$activity->status" /></td>
                <td class="px-4 py-3"><x-document-completeness-badge :completeness="$completeness" /></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="{{ route('activities.show', $activity) }}" class="text-blue-600 hover:underline">Detail</a>
                    @can('update', $activity)
                        <span class="mx-1 text-gray-300">|</span>
                        <a href="{{ route('activities.edit', $activity) }}" class="text-blue-600 hover:underline">Edit</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-empty-state title="Belum ada kegiatan" description="Mulai dengan menambahkan kegiatan baru.">
                        <x-slot:action>
                            @can('create', App\Models\Activity::class)
                                <a href="{{ route('activities.create') }}" class="sipreska-btn-primary">+ Tambah Kegiatan</a>
                            @endcan
                        </x-slot:action>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($activities->hasPages())
        <div class="mt-4">{{ $activities->withQueryString()->links() }}</div>
    @endif
@endsection
