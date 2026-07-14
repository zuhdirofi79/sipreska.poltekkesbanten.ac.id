@extends('layouts.app')

@section('title', 'Koreksi Absensi')
@section('page_title', 'Koreksi Data Peserta')
@section('page_description', $activity->title)

@section('content')
    <x-page-header title="Edit Respons Absensi" />

    <x-card>
        <form method="POST" action="{{ route('activities.daftar-hadir.response.update', [$activity, $response]) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-form-input label="Nama Lengkap" name="name" :value="old('name', $response->name)" required />

            <div class="grid gap-4 md:grid-cols-2">
                <x-form-input
                    label="NIK"
                    name="nik"
                    :value="old('nik', $response->nik)"
                    hint="Opsional. Isi jika peserta memiliki NIK."
                />
                <x-form-input
                    label="NIP"
                    name="nip"
                    :value="old('nip', $response->nip)"
                    hint="Opsional. Isi jika peserta memiliki NIP."
                />
            </div>

            @if ($response->hasLegacyIdentity())
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>Identitas Lama:</strong> {{ $response->legacyIdentity() }}
                    <p class="mt-1 text-xs text-amber-800">Data legacy belum terklasifikasi. Isi NIK/NIP terpisah jika diketahui.</p>
                </div>
            @endif

            <x-form-input label="Jabatan" name="position" :value="old('position', $response->position)" />

            <x-form-select label="Unit Kerja" name="work_unit" required>
                @foreach ($workUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('work_unit', $response->work_unit) === $unit->value)>
                        {{ $unit->label() }}
                    </option>
                @endforeach
            </x-form-select>

            <x-form-input label="Nama Instansi" name="institution_name" :value="old('institution_name', $response->institution_name)" />

            @error('form')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex gap-2">
                <button type="submit" class="sipreska-btn-primary">Simpan</button>
                <a href="{{ route('activities.daftar-hadir.responses', $activity) }}" class="sipreska-btn-secondary">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
