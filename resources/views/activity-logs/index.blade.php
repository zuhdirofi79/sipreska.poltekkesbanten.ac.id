@extends('layouts.app')

@section('title', 'Log Aktivitas')
@section('page_title', 'Log Aktivitas')
@section('page_description', 'Riwayat perubahan data sistem SIPRESKA.')

@section('content')
    <div class="mb-4 sipreska-card p-4">
        <form method="GET" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari aksi atau entitas..." class="sipreska-input lg:col-span-2">
            <select name="action" class="sipreska-select">
                <option value="">Semua Aksi</option>
                @foreach ($actions ?? [] as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <select name="entity_type" class="sipreska-select">
                <option value="">Semua Entitas</option>
                @foreach ($entityTypes ?? [] as $type)
                    <option value="{{ $type }}" @selected(request('entity_type') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="sipreska-btn-secondary w-full">Filter</button>
                <a href="{{ route('activity-logs.index') }}" class="sipreska-btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <x-data-table>
        <x-slot:head>
            <tr>
                <x-data-table-th>Waktu</x-data-table-th>
                <x-data-table-th>Pengguna</x-data-table-th>
                <x-data-table-th>Aksi</x-data-table-th>
                <x-data-table-th>Entitas</x-data-table-th>
                <x-data-table-th>IP</x-data-table-th>
            </tr>
        </x-slot:head>
        @forelse ($logs as $log)
            <tr class="border-b border-gray-100">
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                    {{ \App\Support\DateTimeFormatter::display($log->created_at) }}
                </td>
                <td class="px-4 py-3">{{ $log->actor?->name ?? 'Sistem' }}</td>
                <td class="px-4 py-3"><x-badge variant="blue">{{ $log->action }}</x-badge></td>
                <td class="px-4 py-3">
                    <span class="text-gray-900">{{ class_basename($log->entity_type) }}</span>
                    @if ($log->entity_id)
                        <span class="text-gray-400">#{{ $log->entity_id }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ $log->ip_address ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5"><x-empty-state title="Belum ada log aktivitas" /></td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($logs->hasPages())
        <div class="mt-4">{{ $logs->withQueryString()->links() }}</div>
    @endif
@endsection
