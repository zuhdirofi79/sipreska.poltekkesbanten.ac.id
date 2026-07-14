@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard - Statistik')
@section('page_description', 'Ringkasan kegiatan dan kelengkapan dokumen.')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total Kegiatan', 'value' => $stats['total'] ?? 0, 'color' => 'blue'],
            ['label' => 'Terdaftar', 'value' => $stats['terdaftar'] ?? 0, 'color' => 'blue'],
            ['label' => 'Selesai', 'value' => $stats['selesai'] ?? 0, 'color' => 'green'],
            ['label' => 'Dokumen Belum Lengkap', 'value' => $stats['incomplete'] ?? 0, 'color' => 'red'],
        ] as $stat)
            <div class="sipreska-stat-card">
                <div class="text-sm text-gray-500">{{ $stat['label'] }}</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($stat['value']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card title="Kegiatan per Bulan">
            <div class="space-y-3">
                @forelse ($chartByMonth ?? [] as $row)
                    @php $max = max(1, collect($chartByMonth)->max('total')); @endphp
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-gray-600">
                            <span>{{ $row['label'] }}</span>
                            <span>{{ $row['total'] }}</span>
                        </div>
                        <div class="sipreska-progress-track">
                            <div class="sipreska-progress-bar bg-blue-500" style="width: {{ ($row['total'] / $max) * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <x-empty-state title="Belum ada data" />
                @endforelse
            </div>
        </x-card>

        <x-card title="Kegiatan per Kategori">
            <div class="space-y-3">
                @forelse ($chartByCategory ?? [] as $row)
                    @php $max = max(1, collect($chartByCategory)->max('total')); @endphp
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-gray-600">
                            <span>{{ $row['label'] }}</span>
                            <span>{{ $row['total'] }}</span>
                        </div>
                        <div class="sipreska-progress-track">
                            <div class="sipreska-progress-bar bg-indigo-500" style="width: {{ ($row['total'] / $max) * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <x-empty-state title="Belum ada data" />
                @endforelse
            </div>
        </x-card>
    </div>

    @if (! empty($chartByUnit))
        <div class="mt-6">
            <x-card title="Kegiatan per Unit">
                <canvas id="unitChart" height="120"></canvas>
            </x-card>
        </div>
    @endif

    <div class="mt-6">
        <x-card title="Kegiatan dengan Dokumen Belum Lengkap">
            <x-data-table>
                <x-slot:head>
                    <tr>
                        <x-data-table-th>Judul</x-data-table-th>
                        <x-data-table-th>Tanggal</x-data-table-th>
                        <x-data-table-th>Unit</x-data-table-th>
                        <x-data-table-th>Kelengkapan</x-data-table-th>
                        <x-data-table-th align="right">Aksi</x-data-table-th>
                    </tr>
                </x-slot:head>
                @forelse ($incompleteActivities ?? [] as $item)
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $item['activity']->title }}</td>
                        <td class="px-4 py-3">{{ $item['activity']->activity_date?->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $item['activity']->unit?->short_name ?? '-' }}</td>
                        <td class="px-4 py-3"><x-document-completeness-badge :completeness="$item['completeness']" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('activities.edit', $item['activity']) }}" class="text-blue-600 hover:underline">Lengkapi</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state title="Semua dokumen lengkap" description="Tidak ada kegiatan dengan dokumen belum lengkap." />
                        </td>
                    </tr>
                @endforelse
            </x-data-table>
        </x-card>
    </div>

    @if (! empty($chartByUnit))
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const ctx = document.getElementById('unitChart');
                    if (!ctx) return;
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: @json(collect($chartByUnit)->pluck('label')),
                            datasets: [{
                                label: 'Kegiatan',
                                data: @json(collect($chartByUnit)->pluck('total')),
                                backgroundColor: '#2563eb',
                                borderRadius: 8,
                            }],
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        },
                    });
                });
            </script>
        @endpush
    @endif
@endsection
