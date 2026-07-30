@extends('layouts.app')

@section('title', 'Review Import Absensi Offline')
@section('page_title', 'Review Import Absensi Offline')
@section('page_description', $activity->title)

@section('content')
    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('activities.daftar-hadir.import.create', $activity) }}" class="hover:text-blue-600">Import Offline</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Review #{{ $import->id }}</li>
        </ol>
    </nav>

    <x-page-header title="Review Import Offline #{{ $import->id }}">
        <x-slot:actions>
            <a href="{{ route('activities.daftar-hadir.import.create', $activity) }}" class="sipreska-btn-secondary">Kembali</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @error('form')
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $message }}
        </div>
    @enderror

    <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        <p class="font-semibold">Status: {{ $import->status }}</p>
        @if ($import->notes)
            <p class="mt-1 text-xs text-blue-800 whitespace-pre-line">{{ $import->notes }}</p>
        @endif
        <p class="mt-2 text-xs text-blue-800">
            File: {{ $import->original_filename }} ·
            Data hasil OCR/deteksi tidak langsung masuk database. Periksa dan koreksi dulu, lalu klik Import Final.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <x-card title="Preview Scan">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                    @if (str_starts_with((string) $import->mime_type, 'image/'))
                        <img
                            src="{{ $previewUrl }}"
                            alt="Preview scan daftar hadir"
                            class="max-h-[70vh] w-full object-contain"
                        >
                    @else
                        <iframe
                            src="{{ $previewUrl }}"
                            title="Preview scan daftar hadir"
                            class="h-[70vh] w-full"
                        ></iframe>
                    @endif
                </div>
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm text-blue-600 hover:underline">
                    Buka file original di tab baru
                </a>
            </x-card>
        </div>

        <div class="lg:col-span-8">
            <x-card title="Tabel Review">
                <form
                    method="POST"
                    action="{{ route('activities.daftar-hadir.import.update-review', [$activity, $import]) }}"
                    enctype="multipart/form-data"
                    class="space-y-4"
                >
                    @csrf

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Unit Kerja Default</label>
                            <select name="default_work_unit" class="sipreska-select" @disabled(! $editable)>
                                @foreach ($workUnits as $unit)
                                    <option value="{{ $unit->value }}" @selected(old('default_work_unit', $import->default_work_unit) === $unit->value)>
                                        {{ $unit->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <p class="text-xs text-gray-500">
                                NIP wajib. Jika peserta tidak punya NIP, isi <strong>0</strong> atau <strong>-</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-600">
                                <tr>
                                    <th class="px-2 py-2 font-medium">Pilih</th>
                                    <th class="px-2 py-2 font-medium">No</th>
                                    <th class="px-2 py-2 font-medium">Nama Lengkap</th>
                                    <th class="px-2 py-2 font-medium">NIP</th>
                                    <th class="px-2 py-2 font-medium">Jabatan</th>
                                    <th class="px-2 py-2 font-medium">Tanda Tangan</th>
                                    <th class="px-2 py-2 font-medium">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($import->rows as $row)
                                    <tr class="border-b border-gray-100 align-top">
                                        <td class="px-2 py-2">
                                            <input
                                                type="checkbox"
                                                name="rows[{{ $row->id }}][is_selected]"
                                                value="1"
                                                @checked(old('rows.'.$row->id.'.is_selected', $row->is_selected))
                                                @disabled(! $editable)
                                            >
                                        </td>
                                        <td class="px-2 py-2 text-gray-500">{{ $row->row_number }}</td>
                                        <td class="px-2 py-2">
                                            <input
                                                type="text"
                                                name="rows[{{ $row->id }}][review_name]"
                                                value="{{ old('rows.'.$row->id.'.review_name', $row->review_name ?? $row->detected_name) }}"
                                                class="sipreska-input w-40 min-w-[9rem]"
                                                @disabled(! $editable)
                                                placeholder="Nama lengkap"
                                            >
                                        </td>
                                        <td class="px-2 py-2">
                                            <input
                                                type="text"
                                                name="rows[{{ $row->id }}][review_nip]"
                                                value="{{ old('rows.'.$row->id.'.review_nip', $row->review_nip ?? $row->detected_nip) }}"
                                                class="sipreska-input w-28 min-w-[7rem]"
                                                @disabled(! $editable)
                                                placeholder="0 atau -"
                                            >
                                        </td>
                                        <td class="px-2 py-2">
                                            <input
                                                type="text"
                                                name="rows[{{ $row->id }}][review_position]"
                                                value="{{ old('rows.'.$row->id.'.review_position', $row->review_position ?? $row->detected_position) }}"
                                                class="sipreska-input w-28 min-w-[7rem]"
                                                @disabled(! $editable)
                                                placeholder="Opsional"
                                            >
                                        </td>
                                        <td class="px-2 py-2">
                                            @if ($row->review_signature_path)
                                                <p class="mb-1 text-xs text-green-700">Ada file TT</p>
                                            @endif
                                            @if ($editable)
                                                <input
                                                    type="file"
                                                    name="rows[{{ $row->id }}][signature]"
                                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                                    class="block w-36 text-xs"
                                                >
                                            @else
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2">
                                            <input
                                                type="text"
                                                name="rows[{{ $row->id }}][review_notes]"
                                                value="{{ old('rows.'.$row->id.'.review_notes', $row->review_notes) }}"
                                                class="sipreska-input w-28 min-w-[7rem]"
                                                @disabled(! $editable)
                                                placeholder="Catatan"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($editable)
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <button type="submit" class="sipreska-btn-secondary">Simpan Review</button>
                            <button type="submit" name="add_row" value="1" class="sipreska-btn-secondary">Tambah Baris</button>
                        </div>
                    @endif
                </form>

                @if ($editable)
                    <form
                        method="POST"
                        action="{{ route('activities.daftar-hadir.import.commit', [$activity, $import]) }}"
                        class="mt-4 border-t border-gray-100 pt-4"
                        onsubmit="return confirm('Import final baris yang dipilih ke Partisipan Hadir?')"
                    >
                        @csrf
                        <button type="submit" class="sipreska-btn-primary">Import Final</button>
                        <p class="mt-2 text-xs text-gray-500">
                            Hanya baris tercentang dengan Nama + NIP yang valid yang akan diimport. Duplikat NIP dilewati.
                        </p>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('activities.daftar-hadir.import.destroy', [$activity, $import]) }}"
                        class="mt-3"
                        onsubmit="return confirm('Hapus draft import ini?')"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline">Hapus Draft Import</button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-gray-600">
                        Import sudah selesai.
                        <a href="{{ route('activities.daftar-hadir.responses', $activity) }}" class="text-blue-600 hover:underline">
                            Lihat Partisipan Hadir
                        </a>
                    </p>
                @endif
            </x-card>
        </div>
    </div>
@endsection
