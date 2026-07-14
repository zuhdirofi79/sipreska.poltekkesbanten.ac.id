@extends('layouts.app')

@section('title', 'Tambah Kegiatan')
@section('page_title', 'Tambah Kegiatan')
@section('page_description', 'Buat agenda kegiatan baru beserta informasi dasar.')

@section('content')
    <x-page-header title="Form Kegiatan Baru" description="Lengkapi informasi dasar kegiatan. Dokumen dapat diunggah setelah disimpan." />

    <form method="POST" action="{{ route('activities.store') }}">
        @csrf
        <x-card>
            @include('activities.partials.form')
            @include('activities.partials.document-placeholders')

            <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                <button type="submit" class="sipreska-btn-primary">Simpan dan Lanjutkan</button>
                <a href="{{ route('activities.index') }}" class="sipreska-btn-secondary">Batal</a>
            </div>
        </x-card>
    </form>
@endsection
