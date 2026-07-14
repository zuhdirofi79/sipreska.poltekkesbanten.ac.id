@extends('layouts.app')

@section('title', 'Edit Kegiatan')
@section('page_title', 'Edit Kegiatan')
@section('page_description', $activity->title)

@section('content')
    @php
        $activeTab = request('tab', 'data');
        $tabs = [
            'data' => 'Data Kegiatan',
            'invitation' => 'Surat Undangan',
            'attendance' => 'Daftar Hadir / Absensi',
            'minutes' => 'Notulensi',
            'documentation' => 'Dokumentasi',
            'publication' => 'Publikasi',
        ];
    @endphp

    <x-page-header :title="$activity->title">
        <x-slot:actions>
            <x-document-completeness-badge :completeness="$completeness" />
            <a href="{{ route('activities.show', $activity) }}" class="sipreska-btn-secondary">Lihat Detail</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
        @foreach ($tabs as $key => $label)
            <a
                href="{{ route('activities.edit', [$activity, 'tab' => $key]) }}"
                class="sipreska-tab {{ $activeTab === $key ? 'is-active' : '' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($activeTab === 'data')
        <form method="POST" action="{{ route('activities.update', $activity) }}">
            @csrf
            @method('PUT')
            <x-card>
                @include('activities.partials.form', ['activity' => $activity])

                <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                    <button type="submit" class="sipreska-btn-primary">Simpan Perubahan</button>
                    <a href="{{ route('activities.index') }}" class="sipreska-btn-secondary">Kembali</a>
                </div>
            </x-card>
        </form>
    @elseif ($activeTab === 'invitation')
        @include('activities.partials.invitation')
    @elseif ($activeTab === 'attendance')
        @include('activities.partials.attendance')
    @elseif ($activeTab === 'minutes')
        @include('activities.partials.minutes')
    @elseif ($activeTab === 'documentation')
        @include('activities.partials.documentation')
    @elseif ($activeTab === 'publication')
        @include('activities.partials.publication')
    @endif

    @can('delete', $activity)
        <div class="mt-8">
            <x-card>
                <h3 class="text-sm font-semibold text-red-700">Zona Berbahaya</h3>
                <p class="mt-1 text-sm text-gray-500">Menghapus kegiatan akan menghapus seluruh dokumen terkait.</p>
                <form id="delete-activity-form" method="POST" action="{{ route('activities.destroy', $activity) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <button
                        type="button"
                        class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100"
                        @click="$dispatch('open-confirm', { id: 'delete-activity', form: $el.closest('form') })"
                    >
                        Hapus Kegiatan
                    </button>
                </form>
            </x-card>
        </div>
        <x-confirm-dialog id="delete-activity" title="Hapus Kegiatan?" message="Kegiatan dan seluruh dokumen terkait akan dihapus permanen." confirm-label="Ya, hapus" confirm-class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" />
    @endcan
@endsection
