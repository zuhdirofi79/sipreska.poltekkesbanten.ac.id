@php
    use App\Enums\ActivityStatus;

    $editing = isset($activity);
    $selectedUnitId = old('unit_id', $editing ? $activity->unit_id : auth()->user()?->unit_id);
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-form-input label="Judul Kegiatan" name="title" :value="$editing ? $activity->title : null" required />
    </div>

    <x-form-select
        label="Kategori"
        name="category_id"
        placeholder="Pilih kategori"
        :options="$categories->pluck('name', 'id')"
        :selected="$editing ? $activity->category_id : null"
        required
    />

    <x-form-select
        label="Status"
        name="status"
        :options="collect(ActivityStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])"
        :selected="$editing ? $activity->status?->value : ActivityStatus::Terdaftar->value"
        required
    />

    <x-form-input
        label="Tanggal Kegiatan"
        name="activity_date"
        type="date"
        :value="$editing ? $activity->activity_date?->format('Y-m-d') : null"
        required
    />

    <div class="grid grid-cols-2 gap-3">
        <x-form-input
            label="Waktu Mulai"
            name="start_time"
            type="time"
            :value="$editing && $activity->start_time ? substr($activity->start_time, 0, 5) : null"
        />
        <x-form-input
            label="Waktu Selesai"
            name="end_time"
            type="time"
            :value="$editing && $activity->end_time ? substr($activity->end_time, 0, 5) : null"
        />
    </div>

    <x-form-input label="Lokasi" name="location" :value="$editing ? $activity->location : null" required />

    @if (auth()->user()?->isSuperadmin())
        <x-form-select
            label="Unit Penyelenggara"
            name="unit_id"
            placeholder="Pilih unit"
            :options="$units->pluck('short_name', 'id')"
            :selected="$selectedUnitId"
            required
        />
    @endif

    <x-form-input label="Nama PIC" name="pic_name" :value="$editing ? $activity->pic_name : null" required />

    <x-form-input label="Kontak PIC" name="pic_contact" :value="$editing ? $activity->pic_contact : null" />

    <div class="md:col-span-2">
        <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Deskripsi</label>
        <textarea id="description" name="description" rows="3" class="sipreska-textarea">{{ old('description', $editing ? $activity->description : '') }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label for="objective" class="mb-1 block text-sm font-medium text-gray-700">Tujuan Kegiatan</label>
        <textarea id="objective" name="objective" rows="2" class="sipreska-textarea">{{ old('objective', $editing ? $activity->objective : '') }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label for="target_participants" class="mb-1 block text-sm font-medium text-gray-700">Target Peserta</label>
        <textarea id="target_participants" name="target_participants" rows="2" class="sipreska-textarea">{{ old('target_participants', $editing ? $activity->target_participants : '') }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="mb-1 block text-sm font-medium text-gray-700">Catatan</label>
        <textarea id="notes" name="notes" rows="2" class="sipreska-textarea">{{ old('notes', $editing ? $activity->notes : '') }}</textarea>
    </div>
</div>
