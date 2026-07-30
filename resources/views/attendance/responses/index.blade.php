@extends('layouts.app')

@section('title', 'Partisipan Hadir')
@section('page_title', 'Partisipan Hadir')
@section('page_description', 'Daftar peserta yang telah mengisi form absensi untuk kegiatan ini.')

@section('content')
    @php
        use App\Support\DateTimeFormatter;

        $exportSuffix = $exportQuery !== '' ? '?' . $exportQuery : '';
        $formStatus = $form->isAcceptingSubmissions() ? 'Aktif' : 'Ditutup';
        $pollUrl = route('activities.daftar-hadir.responses.data', $activity);
    @endphp

    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('activities.show', $activity) }}" class="hover:text-blue-600">Detail Kegiatan</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('activities.edit', [$activity, 'tab' => 'attendance']) }}" class="hover:text-blue-600">Daftar Hadir</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Partisipan Hadir</li>
        </ol>
    </nav>

    <x-page-header title="Partisipan Hadir">
        <x-slot:actions>
            <a href="{{ route('activities.daftar-hadir.import.create', $activity) }}" class="sipreska-btn-secondary">Import Offline</a>
            <a href="{{ route('activities.edit', [$activity, 'tab' => 'attendance']) }}" class="sipreska-btn-secondary">Kembali ke Daftar Hadir</a>
        </x-slot:actions>
    </x-page-header>

    <p class="mb-6 text-sm text-gray-600">
        Daftar peserta yang telah mengisi form absensi untuk kegiatan ini.
    </p>

    <x-card title="Ringkasan Kegiatan" class="mb-6">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <dt class="text-gray-500">Judul Kegiatan</dt>
                <dd class="font-medium text-gray-900">{{ $activity->title }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Tanggal</dt>
                <dd class="font-medium text-gray-900">{{ $activity->activity_date?->translatedFormat('d F Y') ?? '-' }}</dd>
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
                <dt class="text-gray-500">Tempat</dt>
                <dd class="font-medium text-gray-900">{{ $activity->location ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Unit Penyelenggara</dt>
                <dd class="font-medium text-gray-900">{{ $activity->unit?->short_name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Status Form</dt>
                <dd class="font-medium text-gray-900" data-stat="form-status">{{ $formStatus }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Total Respons</dt>
                <dd class="font-medium text-gray-900" data-stat="total">{{ $stats['total'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Respons Terakhir</dt>
                <dd class="font-medium text-gray-900" data-stat="last-submitted">{{ DateTimeFormatter::display($stats['last_submitted_at']) }}</dd>
            </div>
        </dl>
    </x-card>

    <div
        class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        x-data="attendanceResponsesPoll(@js(['url' => $pollUrl, 'initialTotal' => $stats['total'], 'initialVersion' => $stats['last_submitted_at']?->toIso8601String()]))"
    >
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-500">Total Partisipan</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900" x-text="stats.total" data-stat-card="total">{{ $stats['total'] }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-500">Respons Hari Ini</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900" x-text="stats.today" data-stat-card="today">{{ $stats['today'] }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-500">Respons Terakhir</div>
            <div class="mt-1 text-sm font-semibold text-gray-900" x-text="stats.last_submitted_at" data-stat-card="last">{{ DateTimeFormatter::display($stats['last_submitted_at']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs text-gray-500">Status Form</div>
            <div class="mt-1 text-sm font-semibold text-gray-900" x-text="stats.form_status" data-stat-card="status">{{ $formStatus }}</div>
        </div>

        <div
            x-show="hasUpdates"
            x-cloak
            class="col-span-full rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 sm:flex sm:items-center sm:justify-between"
        >
            <span>Ada respons baru. Perbarui daftar untuk melihat data terbaru.</span>
            <button type="button" class="sipreska-btn-secondary mt-2 w-full sm:mt-0 sm:w-auto" @click="window.location.reload()">
                Perbarui Data
            </button>
        </div>
    </div>

    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <a href="{{ route('activities.daftar-hadir.export-excel', $activity) }}{{ $exportSuffix }}" class="sipreska-btn-secondary w-full sm:w-auto">Export Excel</a>
        <a href="{{ route('activities.daftar-hadir.export-pdf', $activity) }}{{ $exportSuffix }}" class="sipreska-btn-secondary w-full sm:w-auto">Export PDF</a>
        <a href="{{ route('activities.daftar-hadir.print', $activity) }}{{ $exportSuffix }}" target="_blank" rel="noopener" class="sipreska-btn-secondary w-full sm:w-auto">Print</a>
    </div>

    <div class="mb-4 sipreska-card p-4">
        <form method="GET" action="{{ route('activities.daftar-hadir.responses', $activity) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
            <input
                type="search"
                name="search"
                value="{{ $filters['search'] }}"
                placeholder="Cari nama, NIP, identitas lama, jabatan..."
                class="sipreska-input lg:col-span-2"
            >
            <select name="work_unit" class="sipreska-select">
                <option value="">Semua Unit Kerja</option>
                @foreach ($workUnits as $unit)
                    <option value="{{ $unit->value }}" @selected($filters['work_unit'] === $unit->value)>{{ $unit->label() }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="sipreska-input" aria-label="Tanggal mulai submit">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="sipreska-input" aria-label="Tanggal akhir submit">
            <select name="sort" class="sipreska-select">
                <option value="oldest" @selected($filters['sort'] === 'oldest')>Terlama</option>
                <option value="newest" @selected($filters['sort'] === 'newest')>Terbaru</option>
                <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Nama A–Z</option>
                <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Nama Z–A</option>
            </select>
            <div class="flex gap-2 lg:col-span-6">
                <button type="submit" class="sipreska-btn-primary w-full sm:w-auto">Tampilkan</button>
                <a href="{{ route('activities.daftar-hadir.responses', $activity) }}" class="sipreska-btn-secondary w-full sm:w-auto text-center">Reset</a>
            </div>
        </form>
    </div>

    @if ($responses->isEmpty())
        <x-card>
            <x-empty-state
                title="Belum ada partisipan yang mengisi form absensi."
                description="Bagikan tautan form absensi kepada peserta untuk mulai menerima respons."
            />
        </x-card>
    @else
        <div class="sipreska-card overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">No</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Waktu Submit</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Nama</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">NIP</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Identitas Lama</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Jabatan</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Unit Kerja</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Nama Instansi</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">Tanda Tangan</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($responses as $index => $response)
                        @php
                            $workUnitLabel = \App\Enums\AttendanceWorkUnit::tryFrom($response->work_unit)?->label() ?? $response->work_unit;
                            $signatureUrl = $response->signature_path
                                ? route('files.show', ['type' => 'signature', 'id' => $response->id, 'inline' => 1])
                                : null;
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $responses->firstItem() + $index }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ DateTimeFormatter::display($response->submitted_at) }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $response->name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $response->nip ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($response->hasLegacyIdentity())
                                    <span class="inline-flex items-center gap-1">
                                        <span>{{ $response->legacyIdentity() }}</span>
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Data legacy</span>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $response->position ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $workUnitLabel }}</td>
                            <td class="px-4 py-3">{{ $response->institution_name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                @if ($signatureUrl)
                                    <button
                                        type="button"
                                        class="inline-block rounded border border-gray-200 bg-white p-0.5 hover:border-blue-300"
                                        @click="$dispatch('open-signature-preview', { url: @js($signatureUrl), name: @js($response->name) })"
                                        aria-label="Pratinjau tanda tangan {{ $response->name }}"
                                    >
                                        <img src="{{ $signatureUrl }}" alt="Tanda tangan {{ $response->name }}" class="h-10 w-16 object-contain" loading="lazy">
                                    </button>
                                @else
                                    <span class="text-gray-400">Tidak tersedia</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('activities.daftar-hadir.response.show', [$activity, $response]) }}" class="text-blue-600 hover:underline">Lihat Detail</a>
                                <span class="mx-1 text-gray-300">|</span>
                                <a href="{{ route('activities.daftar-hadir.response.edit', [$activity, $response]) }}" class="text-blue-600 hover:underline">Edit Koreksi</a>
                                <span class="mx-1 text-gray-300">|</span>
                                <form
                                    method="POST"
                                    action="{{ route('activities.daftar-hadir.response.destroy', [$activity, $response]) }}"
                                    class="inline"
                                    x-data
                                    @submit.prevent="$dispatch('open-confirm', { id: 'delete-response-{{ $response->id }}', form: $el })"
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
            {{ $responses->links() }}
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

    @foreach ($responses as $response)
        <x-confirm-dialog
            :id="'delete-response-' . $response->id"
            title="Hapus Respons Absensi"
            message="Apakah Anda yakin ingin menghapus data peserta {{ $response->name }}? Tindakan ini tidak dapat dibatalkan."
            confirmLabel="Ya, hapus"
            confirmClass="sipreska-btn-primary bg-red-600 hover:bg-red-700"
        />
    @endforeach
@endsection
