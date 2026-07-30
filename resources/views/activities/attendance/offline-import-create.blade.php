@extends('layouts.app')

@section('title', 'Import Absensi Offline')
@section('page_title', 'Import Absensi Offline')
@section('page_description', $activity->title)

@section('content')
    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('activities.edit', [$activity, 'tab' => 'attendance']) }}" class="hover:text-blue-600">Daftar Hadir</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Import Offline</li>
        </ol>
    </nav>

    <x-page-header title="Import Absensi Offline">
        <x-slot:actions>
            <a href="{{ route('activities.edit', [$activity, 'tab' => 'attendance']) }}" class="sipreska-btn-secondary">Kembali</a>
            <a href="{{ route('activities.daftar-hadir.template-offline', $activity) }}" class="sipreska-btn-primary">
                Download Template Kertas
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-semibold">Alur resmi SIPRESKA</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Unduh template kertas daftar hadir dari SIPRESKA.</li>
            <li>Cetak, isi Nama / NIP / Jabatan / Tanda Tangan peserta.</li>
            <li>Scan atau foto hasil kertas menjadi PDF/JPG/PNG.</li>
            <li>Upload di halaman ini, lalu review sebelum Import Final.</li>
        </ol>
        <p class="mt-2 text-xs text-amber-800">
            Jika file bukan dari template SIPRESKA, deteksi otomatis mungkin tidak akurat dan data harus diisi manual di halaman review.
        </p>
    </div>

    <x-card title="Upload Hasil Scan / Foto">
        <form
            method="POST"
            action="{{ route('activities.daftar-hadir.import.store', $activity) }}"
            enctype="multipart/form-data"
            class="space-y-4"
        >
            @csrf

            <div>
                <label for="scan" class="mb-1 block text-sm font-medium text-gray-700">
                    File Scan Template <span class="text-red-500">*</span>
                </label>
                <input
                    id="scan"
                    type="file"
                    name="scan"
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    required
                    class="sipreska-input w-full"
                >
                <p class="mt-1 text-xs text-gray-500">Format: PDF, JPG, JPEG, PNG. Maksimal 10 MB.</p>
                @error('scan')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="sipreska-btn-primary">Upload &amp; Lanjut ke Review</button>
        </form>
    </x-card>

    @if ($imports->isNotEmpty())
        <x-card title="Riwayat Import Offline" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">Waktu</th>
                            <th class="px-3 py-2 font-medium">File</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 font-medium">Baris</th>
                            <th class="px-3 py-2 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($imports as $item)
                            <tr class="border-b border-gray-100">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ \App\Support\DateTimeFormatter::display($item->created_at) }}
                                </td>
                                <td class="px-3 py-2">{{ $item->original_filename }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    {{ $item->imported_rows_count }}/{{ $item->detected_rows_count }}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <a
                                        href="{{ route('activities.daftar-hadir.import.review', [$activity, $item]) }}"
                                        class="text-blue-600 hover:underline"
                                    >Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
