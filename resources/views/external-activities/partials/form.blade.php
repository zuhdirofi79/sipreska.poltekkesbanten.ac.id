@php
    use App\Enums\ExternalActivityStatus;

    $editing = isset($activity);
    $oldDates = old('dates');
    $dateValues = is_array($oldDates) && $oldDates !== []
        ? array_values($oldDates)
        : ($editing ? $activity->dates->map(fn ($date) => $date->event_date?->format('Y-m-d'))->all() : ['']);
    $lockedDates = $editing
        ? $activity->dates
            ->filter(fn ($date) => ($date->participants_count ?? 0) > 0 || $date->hasParticipants())
            ->map(fn ($date) => $date->event_date?->format('Y-m-d'))
            ->values()
            ->all()
        : [];
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-form-input label="Nama Kegiatan" name="title" :value="$editing ? $activity->title : null" required />
    </div>

    <x-form-select
        label="Status"
        name="status"
        :options="collect(ExternalActivityStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
        :selected="$editing ? $activity->status?->value : ExternalActivityStatus::Terdaftar->value"
        required
    />

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Form Absensi Publik</label>
        <select name="is_open" class="sipreska-select">
            <option value="1" @selected((string) old('is_open', $editing ? (int) $activity->is_open : 1) === '1')>Terbuka</option>
            <option value="0" @selected((string) old('is_open', $editing ? (int) $activity->is_open : 1) === '0')>Ditutup</option>
        </select>
    </div>

    <x-form-input
        label="Dibuka pada"
        name="opens_at"
        type="datetime-local"
        :value="$editing && $activity->opens_at ? $activity->opens_at->format('Y-m-d\TH:i') : null"
    />

    <x-form-input
        label="Ditutup pada"
        name="closes_at"
        type="datetime-local"
        :value="$editing && $activity->closes_at ? $activity->closes_at->format('Y-m-d\TH:i') : null"
    />

    <div class="md:col-span-2 space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Lokasi Kegiatan</h3>
            <p class="mt-1 text-xs text-gray-500">
                Kosongkan keduanya jika lokasi belum ditentukan. Isi salah satu jika hanya offline atau online. Isi keduanya jika kegiatan bersifat hybrid.
            </p>
        </div>

        <div>
            <label for="location_address" class="mb-1 block text-sm font-medium text-gray-700">Alamat / Lokasi Offline</label>
            <textarea
                id="location_address"
                name="location_address"
                rows="3"
                class="sipreska-textarea"
            >{{ old('location_address', $editing ? $activity->location_address : '') }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Opsional. Isi alamat jika kegiatan berlangsung secara offline.</p>
            @error('location_address')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-form-input
            label="Link Kegiatan Online"
            name="online_url"
            type="url"
            :value="$editing ? $activity->online_url : null"
            hint="Opsional. Isi link jika kegiatan memiliki sesi online."
        />
    </div>

    <div
        class="md:col-span-2"
        x-data="externalActivityDates(@js($dateValues), @js($lockedDates))"
    >
        <label class="mb-1 block text-sm font-medium text-gray-700">
            Tanggal Kegiatan <span class="text-red-500">*</span>
        </label>
        <p class="mb-3 text-xs text-gray-500">
            Tambahkan satu atau lebih tanggal secara dinamis. Tanggal yang sudah memiliki absensi tidak dapat dihapus.
        </p>

        <div class="space-y-2">
            <template x-for="(date, index) in dates" :key="index">
                <div class="flex items-center gap-2">
                    <input
                        type="date"
                        name="dates[]"
                        x-model="dates[index]"
                        class="sipreska-input"
                        required
                    >
                    <button
                        type="button"
                        class="sipreska-btn-secondary"
                        x-show="canRemove(index)"
                        @click="remove(index)"
                    >
                        Hapus
                    </button>
                    <span class="text-xs text-gray-500" x-show="isLocked(dates[index])">Sudah ada absensi</span>
                </div>
            </template>
        </div>

        <button type="button" class="sipreska-btn-secondary mt-3" @click="add()">+ Tambah Tanggal</button>
        @error('dates')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error('dates.*')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @include('external-activities.partials.question-builder')
</div>
