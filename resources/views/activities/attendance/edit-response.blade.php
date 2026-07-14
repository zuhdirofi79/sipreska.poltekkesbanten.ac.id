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
            <x-form-input label="NIP / NIK" name="nip_nik" :value="old('nip_nik', $response->nip_nik)" required />
            <x-form-input label="Jabatan" name="position" :value="old('position', $response->position)" />

            <x-form-select label="Unit Kerja" name="work_unit" required>
                @foreach ($workUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('work_unit', $response->work_unit) === $unit->value)>
                        {{ $unit->label() }}
                    </option>
                @endforeach
            </x-form-select>

            <x-form-input label="Nama Instansi" name="institution_name" :value="old('institution_name', $response->institution_name)" />

            <div class="flex gap-2">
                <button type="submit" class="sipreska-btn-primary">Simpan</button>
                <a href="{{ route('activities.daftar-hadir.responses', $activity) }}" class="sipreska-btn-secondary">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
