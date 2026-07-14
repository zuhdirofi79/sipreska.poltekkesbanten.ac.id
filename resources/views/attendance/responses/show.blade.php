@extends('layouts.app')

@section('title', 'Detail Partisipan')
@section('page_title', 'Detail Partisipan Hadir')
@section('page_description', $response->name)

@section('content')
    @php use App\Support\DateTimeFormatter; @endphp

    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('activities.show', $activity) }}" class="hover:text-blue-600">Detail Kegiatan</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('activities.daftar-hadir.responses', $activity) }}" class="hover:text-blue-600">Partisipan Hadir</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Detail</li>
        </ol>
    </nav>

    <x-page-header title="Detail Partisipan Hadir">
        <x-slot:actions>
            <a href="{{ route('activities.daftar-hadir.responses', $activity) }}" class="sipreska-btn-secondary">Kembali</a>
            <a href="{{ route('activities.daftar-hadir.response.edit', [$activity, $response]) }}" class="sipreska-btn-primary">Edit Koreksi</a>
        </x-slot:actions>
    </x-page-header>

    <x-card title="{{ $response->name }}">
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-gray-500">Nama</dt>
                <dd class="font-medium text-gray-900">{{ $response->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">NIP / NIK</dt>
                <dd class="font-medium text-gray-900">{{ $response->nip_nik }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Jabatan</dt>
                <dd class="font-medium text-gray-900">{{ $response->position ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Unit Kerja</dt>
                <dd class="font-medium text-gray-900">{{ $workUnitLabel }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Nama Instansi</dt>
                <dd class="font-medium text-gray-900">{{ $response->institution_name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Waktu Submit</dt>
                <dd class="font-medium text-gray-900">{{ DateTimeFormatter::display($response->submitted_at) }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Tanda Tangan</dt>
                <dd class="mt-2">
                    @if ($response->signature_path)
                        <img
                            src="{{ route('files.show', ['type' => 'signature', 'id' => $response->id, 'inline' => 1]) }}"
                            alt="Tanda tangan {{ $response->name }}"
                            class="max-h-40 rounded border border-gray-200 bg-white p-2"
                        >
                    @else
                        <span class="text-gray-400">Tidak tersedia</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">IP Address</dt>
                <dd class="font-medium text-gray-900">{{ $response->ip_address ?? '-' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500">User Agent</dt>
                <dd class="font-medium text-gray-900 break-all">{{ $response->user_agent ?? '-' }}</dd>
            </div>
        </dl>
    </x-card>
@endsection
