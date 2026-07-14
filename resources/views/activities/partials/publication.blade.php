@php
    use App\Enums\PublicationPlatform;

    $routesReady = Route::has('activities.publikasi.store')
        && Route::has('activities.publikasi.update')
        && Route::has('activities.publikasi.destroy');
@endphp

<x-card title="Publikasi">
    @unless ($routesReady)
        @include('activities.partials.route-unavailable', ['feature' => 'Publikasi'])
    @else
    @if ($activity->publications->isNotEmpty())
        <div class="mb-2">
            <x-badge variant="green">{{ $activity->publications->count() }} publikasi</x-badge>
        </div>
        <div class="mb-4 space-y-4">
            @foreach ($activity->publications as $publication)
                <div class="rounded-lg border border-gray-200 p-4" x-data="{ editing: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="text-sm">
                            <div class="font-medium text-gray-900">{{ $publication->title }}</div>
                            <div class="mt-1 text-gray-500">
                                {{ $publication->platform?->label() ?? $publication->platform }}
                                &middot;
                                {{ $publication->published_at?->translatedFormat('d M Y') ?? '-' }}
                            </div>
                            <a href="{{ $publication->url }}" target="_blank" rel="noopener" class="mt-1 inline-block break-all text-blue-600 hover:underline">{{ $publication->url }}</a>
                            @if ($publication->notes)
                                <p class="mt-1 text-xs text-gray-500">{{ $publication->notes }}</p>
                            @endif
                        </div>
                        @can('manageDocuments', $activity)
                            <div class="flex gap-2">
                                <button type="button" class="sipreska-btn-secondary" @click="editing = !editing">Edit</button>
                                <form id="delete-pub-{{ $publication->id }}-form" method="POST" action="{{ route('activities.publikasi.destroy', [$activity, $publication]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100" @click="$dispatch('open-confirm', { id: 'delete-pub-{{ $publication->id }}', form: $el.closest('form') })">Hapus</button>
                                </form>
                                <x-confirm-dialog id="delete-pub-{{ $publication->id }}" title="Hapus Publikasi?" message="Entri publikasi akan dihapus." confirm-label="Ya, hapus" confirm-class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" />
                            </div>
                        @endcan
                    </div>
                    @can('manageDocuments', $activity)
                        <form x-show="editing" x-cloak method="POST" action="{{ route('activities.publikasi.update', [$activity, $publication]) }}" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-3 md:grid-cols-2">
                                <x-form-select
                                    label="Platform"
                                    name="platform"
                                    :options="collect(PublicationPlatform::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])"
                                    :selected="old('platform', $publication->platform?->value)"
                                    required
                                />
                                <x-form-input label="Judul Publikasi" name="title" :value="old('title', $publication->title)" required />
                                <x-form-input label="URL" name="url" type="url" :value="old('url', $publication->url)" required />
                                <x-form-input label="Tanggal Publikasi" name="published_at" type="date" :value="old('published_at', $publication->published_at?->format('Y-m-d'))" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Catatan</label>
                                <textarea name="notes" rows="2" class="sipreska-textarea">{{ old('notes', $publication->notes) }}</textarea>
                            </div>
                            <button type="submit" class="sipreska-btn-primary">Simpan Perubahan</button>
                        </form>
                    @endcan
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state title="Belum ada publikasi" description="Tambahkan tautan publikasi kegiatan di media sosial atau website." />
    @endif

    @can('manageDocuments', $activity)
        <form method="POST" action="{{ route('activities.publikasi.store', $activity) }}" class="mt-4 space-y-4 border-t border-gray-100 pt-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <x-form-select
                    label="Platform"
                    name="platform"
                    :options="collect(PublicationPlatform::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])"
                    :selected="old('platform')"
                    required
                />
                <x-form-input label="Judul Publikasi" name="title" :value="old('title')" required />
                <x-form-input label="URL" name="url" type="url" :value="old('url')" required />
                <x-form-input label="Tanggal Publikasi" name="published_at" type="date" :value="old('published_at')" />
            </div>
            <div>
                <label for="publication_notes" class="mb-1 block text-sm font-medium text-gray-700">Catatan</label>
                <textarea id="publication_notes" name="notes" rows="2" class="sipreska-textarea">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="sipreska-btn-primary">Tambah Publikasi</button>
        </form>
    @endcan
    @endunless
</x-card>
