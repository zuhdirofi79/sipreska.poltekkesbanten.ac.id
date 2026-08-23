@extends('layouts.app')

@section('title', 'Tambah Kegiatan Eksternal')
@section('page_title', 'Tambah Kegiatan Eksternal')
@section('page_description', 'Buat agenda kegiatan eksternal beserta tanggal kehadiran dinamis.')

@section('content')
    <x-page-header title="Form Kegiatan Eksternal Baru" description="Lengkapi nama kegiatan dan satu atau lebih tanggal." />

    <form method="POST" action="{{ route('external-activities.store') }}">
        @csrf
        <x-card>
            @include('external-activities.partials.form')

            <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                <button type="submit" class="sipreska-btn-primary">Simpan</button>
                <a href="{{ route('external-activities.index') }}" class="sipreska-btn-secondary">Batal</a>
            </div>
        </x-card>
    </form>
@endsection
