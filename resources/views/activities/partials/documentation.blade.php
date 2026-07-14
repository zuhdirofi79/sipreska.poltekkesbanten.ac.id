@php
    use App\Enums\DocumentationType;

    $formatBytes = static function (?int $bytes): string {
        if ($bytes === null || $bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format($bytes / 1024, 0).' KB';
    };

    $routesReady = Route::has('activities.dokumentasi.store')
        && Route::has('activities.dokumentasi.update')
        && Route::has('activities.dokumentasi.view')
        && Route::has('activities.dokumentasi.download')
        && Route::has('activities.dokumentasi.thumbnail')
        && Route::has('activities.dokumentasi.destroy');
@endphp

<x-card title="Dokumentasi">
    @unless ($routesReady)
        @include('activities.partials.route-unavailable', ['feature' => 'Dokumentasi'])
    @else
    @if ($activity->documentations->isNotEmpty())
        <div class="mb-2">
            <x-badge variant="green">{{ $activity->documentations->count() }} dokumentasi</x-badge>
        </div>
        <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($activity->documentations as $doc)
                <div class="overflow-hidden rounded-lg border border-gray-200" x-data="{ editing: false }">
                    <a href="{{ route('activities.dokumentasi.view', [$activity, $doc]) }}" target="_blank" rel="noopener" class="block">
                        <img
                            src="{{ route('activities.dokumentasi.thumbnail', [$activity, $doc]) }}"
                            alt="{{ $doc->title ?: $doc->original_name }}"
                            class="h-40 w-full object-cover bg-gray-100"
                            loading="lazy"
                        >
                    </a>
                    <div class="space-y-2 p-3 text-sm">
                        <div class="font-medium text-gray-900">{{ $doc->title ?: $doc->original_name }}</div>
                        <div class="text-xs text-gray-500">{{ $doc->type?->label() ?? $doc->type }}</div>
                        @if ($doc->description)
                            <p class="text-xs text-gray-500">{{ $doc->description }}</p>
                        @endif
                        <div class="text-xs text-gray-400">
                            {{ $formatBytes($doc->size) }}
                            &middot;
                            {{ $doc->created_at?->translatedFormat('d M Y') ?? '-' }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('activities.dokumentasi.view', [$activity, $doc]) }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Pratinjau</a>
                            <a href="{{ route('activities.dokumentasi.download', [$activity, $doc]) }}" class="text-blue-600 hover:underline">Unduh</a>
                            @can('manageDocuments', $activity)
                                <button type="button" class="text-blue-600 hover:underline" @click="editing = !editing">Edit</button>
                                <form id="delete-doc-{{ $doc->id }}-form" method="POST" action="{{ route('activities.dokumentasi.destroy', [$activity, $doc]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="text-red-600 hover:underline" @click="$dispatch('open-confirm', { id: 'delete-doc-{{ $doc->id }}', form: $el.closest('form') })">Hapus</button>
                                </form>
                                <x-confirm-dialog id="delete-doc-{{ $doc->id }}" title="Hapus Dokumentasi?" message="File dokumentasi akan dihapus permanen." confirm-label="Ya, hapus" confirm-class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" />
                            @endcan
                        </div>
                        @can('manageDocuments', $activity)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('activities.dokumentasi.update', [$activity, $doc]) }}" class="mt-2 space-y-2 border-t border-gray-100 pt-2">
                                @csrf
                                @method('PUT')
                                <x-form-input label="Judul" name="title" :value="old('title', $doc->title)" />
                                <x-form-select
                                    label="Jenis"
                                    name="type"
                                    :options="collect(DocumentationType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])"
                                    :selected="old('type', $doc->type?->value)"
                                    required
                                />
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-700">Keterangan</label>
                                    <textarea name="description" rows="2" class="sipreska-textarea">{{ old('description', $doc->description) }}</textarea>
                                </div>
                                <button type="submit" class="sipreska-btn-primary text-xs">Simpan</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state title="Belum diunggah" description="Unggah foto, banner, poster, atau materi visual kegiatan." />
    @endif

    @can('manageDocuments', $activity)
        <form method="POST" action="{{ route('activities.dokumentasi.store', $activity) }}" enctype="multipart/form-data" class="mt-4 space-y-4 border-t border-gray-100 pt-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <x-form-input label="Judul (opsional)" name="title" :value="old('title')" />
                <x-form-select
                    label="Jenis Dokumentasi"
                    name="type"
                    :options="collect(DocumentationType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])"
                    :selected="old('type', DocumentationType::FotoKegiatan->value)"
                    required
                />
            </div>
            <div>
                <label for="documentation_description" class="mb-1 block text-sm font-medium text-gray-700">Keterangan</label>
                <textarea id="documentation_description" name="description" rows="2" class="sipreska-textarea">{{ old('description') }}</textarea>
            </div>
            <div>
                <label for="documentation_file" class="mb-1 block text-sm font-medium text-gray-700">File Dokumentasi (JPG/PNG/WEBP)</label>
                <input id="documentation_file" name="file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sipreska-input" required>
                @error('file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="sipreska-btn-primary">Tambah Dokumentasi</button>
        </form>
    @endcan
    @endunless
</x-card>
