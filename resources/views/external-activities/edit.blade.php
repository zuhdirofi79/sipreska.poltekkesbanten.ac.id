@extends('layouts.app')

@section('title', 'Edit Kegiatan Eksternal')
@section('page_title', 'Edit Kegiatan Eksternal')
@section('page_description', $activity->title)

@section('content')
    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('external-activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan Eksternal</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('external-activities.show', $activity) }}" class="hover:text-blue-600">Detail</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Edit</li>
        </ol>
    </nav>

    <x-page-header title="Edit Kegiatan Eksternal" :description="$activity->title" />

    <form method="POST" action="{{ route('external-activities.update', $activity) }}">
        @csrf
        @method('PUT')
        <x-card>
            @include('external-activities.partials.form')

            <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                <button type="submit" class="sipreska-btn-primary">Simpan Perubahan</button>
                <a href="{{ route('external-activities.show', $activity) }}" class="sipreska-btn-secondary">Batal</a>
            </div>
        </x-card>
    </form>
@endsection
