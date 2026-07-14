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

    $routesReady = Route::has('activities.surat-undangan.store')
        && Route::has('activities.surat-undangan.view')
        && Route::has('activities.surat-undangan.download')
        && Route::has('activities.surat-undangan.destroy');
@endphp

<x-card title="Surat Undangan">
    @unless ($routesReady)
        @include('activities.partials.route-unavailable', ['feature' => 'Surat Undangan'])
    @else
        @if ($activity->invitationDocument)
            @php $doc = $activity->invitationDocument; @endphp
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <x-badge variant="green">Sudah tersedia</x-badge>
                        <div class="mt-2 font-medium text-gray-900">{{ $doc->original_name }}</div>
                        <div class="mt-1 text-gray-500">
                            No. {{ $doc->letter_number ?? '-' }}
                            &middot;
                            {{ $doc->letter_date?->translatedFormat('d F Y') ?? '-' }}
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
                        <a href="{{ route('activities.surat-undangan.view', $activity) }}" target="_blank" rel="noopener" class="sipreska-btn-secondary">Pratinjau</a>
                        <a href="{{ route('activities.surat-undangan.download', $activity) }}" class="sipreska-btn-secondary">Unduh</a>
                        @can('manageDocuments', $activity)
                            <form id="delete-invitation-form" method="POST" action="{{ route('activities.surat-undangan.destroy', $activity) }}">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100" @click="$dispatch('open-confirm', { id: 'delete-invitation', form: $el.closest('form') })">Hapus</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @else
            <x-empty-state title="Belum diunggah" description="Unggah file PDF surat undangan kegiatan." />
        @endif

        @can('manageDocuments', $activity)
            <form method="POST" action="{{ route('activities.surat-undangan.store', $activity) }}" enctype="multipart/form-data" class="mt-4 space-y-4 border-t border-gray-100 pt-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <x-form-input label="Nomor Surat" name="letter_number" :value="old('letter_number', $activity->invitationDocument?->letter_number)" />
                    <x-form-input label="Tanggal Surat" name="letter_date" type="date" :value="old('letter_date', $activity->invitationDocument?->letter_date?->format('Y-m-d'))" />
                </div>
                <div>
                    <label for="invitation_file" class="mb-1 block text-sm font-medium text-gray-700">File Surat Undangan (PDF)</label>
                    <input id="invitation_file" name="file" type="file" accept=".pdf,application/pdf" class="sipreska-input" @if (! $activity->invitationDocument) required @endif>
                    @error('file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="sipreska-btn-primary">{{ $activity->invitationDocument ? 'Ganti File' : 'Unggah' }} Surat Undangan</button>
            </form>
            <x-confirm-dialog id="delete-invitation" title="Hapus Surat Undangan?" message="File surat undangan akan dihapus permanen." confirm-label="Ya, hapus" confirm-class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" />
        @endcan
    @endunless
</x-card>
