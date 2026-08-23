@extends('layouts.app')

@section('title', 'Kalender Kegiatan')
@section('page_title', 'Kalender Kegiatan')
@section('page_description', 'Tampilan agenda kegiatan per bulan, minggu, hari, dan daftar.')

@section('content')
    <div
        x-data="calendarView(@js($events), '{{ request('view', 'month') }}')"
        class="space-y-4"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button type="button" class="sipreska-btn-secondary" @click="prev()">&larr;</button>
                <button type="button" class="sipreska-btn-secondary" @click="goToday()">Hari Ini</button>
                <button type="button" class="sipreska-btn-secondary" @click="next()">&rarr;</button>
                <h2 class="ml-2 text-lg font-semibold text-gray-900" x-text="titleLabel"></h2>
            </div>
            <div class="flex flex-wrap gap-1">
                @foreach (['month' => 'Bulan', 'week' => 'Minggu', 'day' => 'Hari', 'list' => 'Daftar'] as $viewKey => $viewLabel)
                    <button
                        type="button"
                        class="sipreska-tab"
                        :class="{ 'is-active': view === '{{ $viewKey }}' }"
                        @click="setView('{{ $viewKey }}')"
                    >
                        {{ $viewLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Month view --}}
        <div x-show="view === 'month'" x-cloak class="sipreska-card overflow-hidden">
            <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-semibold uppercase text-gray-500">
                <template x-for="day in ['Min','Sen','Sel','Rab','Kam','Jum','Sab']" :key="day">
                    <div class="px-2 py-3" x-text="day"></div>
                </template>
            </div>
            <div class="grid grid-cols-7">
                <template x-for="cell in monthCells" :key="cell.key">
                    <div class="sipreska-calendar-cell min-h-28 border-b border-r border-gray-100 p-2" :class="{ 'bg-gray-50/50': !cell.inMonth }">
                        <div class="text-xs font-medium" :class="cell.isToday ? 'text-blue-600' : 'text-gray-500'" x-text="cell.day"></div>
                        <div class="mt-1 space-y-1">
                            <template x-for="event in cell.events.slice(0, 3)" :key="event.id">
                                <a :href="event.url" class="block truncate rounded px-1 py-0.5 text-xs text-white" :style="{ backgroundColor: event.color }" x-text="event.title"></a>
                            </template>
                            <div x-show="cell.events.length > 3" class="text-xs text-gray-400" x-text="'+' + (cell.events.length - 3) + ' lainnya'"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Week view --}}
        <div x-show="view === 'week'" x-cloak class="sipreska-card overflow-hidden">
            <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-semibold uppercase text-gray-500">
                <template x-for="cell in weekCells" :key="cell.key">
                    <div class="px-2 py-3">
                        <div x-text="cell.label"></div>
                        <div class="text-base font-bold text-gray-900" x-text="cell.day"></div>
                    </div>
                </template>
            </div>
            <div class="grid grid-cols-7">
                <template x-for="cell in weekCells" :key="'w-' + cell.key">
                    <div class="min-h-40 border-r border-gray-100 p-2">
                        <template x-for="event in cell.events" :key="event.id">
                            <a :href="event.url" class="mb-1 block rounded px-2 py-1 text-xs text-white" :style="{ backgroundColor: event.color }">
                                <span x-text="event.title"></span>
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Day view --}}
        <div x-show="view === 'day'" x-cloak class="sipreska-card p-4">
            <template x-if="dayEvents.length === 0">
                <x-empty-state title="Tidak ada kegiatan" description="Tidak ada kegiatan pada tanggal ini." />
            </template>
            <div class="space-y-3">
                <template x-for="event in dayEvents" :key="event.id">
                    <a :href="event.url" class="flex items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50">
                        <div class="mt-1 h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: event.color }"></div>
                        <div>
                            <div class="font-medium text-gray-900" x-text="event.title"></div>
                            <div class="text-sm text-gray-500" x-text="event.time + ' · ' + event.location"></div>
                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold" :style="{ backgroundColor: event.color + '22', color: event.color }" x-text="event.statusLabel"></span>
                        </div>
                    </a>
                </template>
            </div>
        </div>

        {{-- List view --}}
        <div x-show="view === 'list'" x-cloak>
            <x-data-table>
                <x-slot:head>
                    <tr>
                        <x-data-table-th>Tanggal</x-data-table-th>
                        <x-data-table-th>Judul</x-data-table-th>
                        <x-data-table-th>Unit</x-data-table-th>
                        <x-data-table-th>Status</x-data-table-th>
                    </tr>
                </x-slot:head>
                <template x-for="event in sortedEvents" :key="'list-' + event.id">
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 whitespace-nowrap" x-text="event.dateLabel"></td>
                        <td class="px-4 py-3"><a :href="event.url" class="font-medium text-blue-600 hover:underline" x-text="event.title"></a></td>
                        <td class="px-4 py-3" x-text="event.unit"></td>
                        <td class="px-4 py-3">
                            <span class="sipreska-badge" :style="{ backgroundColor: event.color + '22', color: event.color }" x-text="event.statusLabel"></span>
                        </td>
                    </tr>
                </template>
            </x-data-table>
            <div x-show="sortedEvents.length === 0" class="sipreska-card">
                <x-empty-state title="Tidak ada kegiatan" description="Belum ada kegiatan dalam rentang waktu ini." />
            </div>
        </div>

        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
            @foreach (\App\Enums\ActivityStatus::cases() as $status)
                <span class="inline-flex items-center gap-1">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $status->color() }}"></span>
                    {{ $status->label() }}
                </span>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
            <span class="inline-flex items-center gap-1">
                <span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                Agenda Internal (warna sesuai status)
            </span>
            <span class="inline-flex items-center gap-1">
                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ \App\Enums\ExternalActivityStatus::CALENDAR_COLOR }}"></span>
                Agenda Eksternal
            </span>
        </div>
    </div>
@endsection
