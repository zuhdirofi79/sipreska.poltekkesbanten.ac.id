@php
    $formatBytes = static function (?int $bytes): string {
        if ($bytes === null || $bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format($bytes / 1024, 0).' KB';
    };

    $routesReady = Route::has('activities.notulensi.store')
        && Route::has('activities.notulensi.view')
        && Route::has('activities.notulensi.download')
        && Route::has('activities.notulensi.destroy');
@endphp

<x-card title="Notulensi">
    @unless ($routesReady)
        @include('activities.partials.route-unavailable', ['feature' => 'Notulensi'])
    @else
    @if ($activity->minutesDocument)
        @php $doc = $activity->minutesDocument; @endphp
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <x-badge variant="green">Sudah tersedia</x-badge>
                    <div class="mt-2 font-medium text-gray-900">{{ $doc->original_name }}</div>
                    <div class="mt-1 text-gray-500">
                        Tanggal: {{ $doc->minutes_date?->translatedFormat('d F Y') ?? '-' }}
                        &middot;
                        Notulis: {{ $doc->notulist_name ?? '-' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $formatBytes($doc->size) }}
                        &middot;
                        Diunggah {{ $doc->created_at?->translatedFormat('d M Y H:i') ?? '-' }}
                        @if ($doc->uploader)
                            &middot; {{ $doc->uploader->name }}
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($doc->mime_type === 'application/pdf')
                        <a href="{{ route('activities.notulensi.view', $activity) }}" target="_blank" rel="noopener" class="sipreska-btn-secondary">Pratinjau</a>
                    @endif
                    <a href="{{ route('activities.notulensi.download', $activity) }}" class="sipreska-btn-secondary">Unduh</a>
                    @can('manageDocuments', $activity)
                        <form id="delete-minutes-form" method="POST" action="{{ route('activities.notulensi.destroy', $activity) }}">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100" @click="$dispatch('open-confirm', { id: 'delete-minutes', form: $el.closest('form') })">Hapus</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    @else
        <x-empty-state title="Notulensi belum diunggah" description="Unggah berita acara atau notulensi kegiatan (PDF/DOC/DOCX)." />
    @endif

    @can('manageDocuments', $activity)
        <form method="POST" action="{{ route('activities.notulensi.store', $activity) }}" enctype="multipart/form-data" class="mt-4 space-y-4 border-t border-gray-100 pt-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <x-form-input label="Tanggal Notulensi" name="minutes_date" type="date" :value="old('minutes_date', $activity->minutesDocument?->minutes_date?->format('Y-m-d'))" />
                <x-form-input label="Nama Notulis" name="notulist_name" :value="old('notulist_name', $activity->minutesDocument?->notulist_name)" />
            </div>
            <div>
                <label for="minutes_file" class="mb-1 block text-sm font-medium text-gray-700">File Notulensi</label>
                <input id="minutes_file" name="file" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="sipreska-input" @if (! $activity->minutesDocument) required @endif>
                @error('file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="sipreska-btn-primary">{{ $activity->minutesDocument ? 'Ganti File' : 'Unggah' }} Notulensi</button>
        </form>
        <x-confirm-dialog id="delete-minutes" title="Hapus Notulensi?" message="File notulensi akan dihapus permanen." confirm-label="Ya, hapus" confirm-class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" />
    @endcan
    @endunless
</x-card>
