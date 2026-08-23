@extends('layouts.app')

@section('title', 'Detail Peserta Eksternal')
@section('page_title', 'Detail Peserta')
@section('page_description', $participant->full_name)

@section('content')
    @php
        use App\Support\DateTimeFormatter;
        $signatureUrl = $participant->signature_path
            ? route('external-activities.files.signature', [$activity, $participant, 'inline' => 1])
            : null;
    @endphp

    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('external-activities.index') }}" class="hover:text-blue-600">Agenda Kegiatan Eksternal</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('external-activities.show', $activity) }}" class="hover:text-blue-600">Detail</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('external-activities.attendance.index', $activity) }}" class="hover:text-blue-600">Daftar Hadir</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Peserta</li>
        </ol>
    </nav>

    <x-page-header :title="$participant->full_name">
        <x-slot:actions>
            <a href="{{ route('external-activities.attendance.edit', [$activity, $participant]) }}" class="sipreska-btn-primary">Koreksi Admin</a>
            <a href="{{ route('external-activities.attendance.index', $activity) }}" class="sipreska-btn-secondary">Kembali</a>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-gray-500">Full Name</dt>
                <dd class="font-medium text-gray-900">{{ $participant->full_name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Institution</dt>
                <dd class="font-medium text-gray-900">{{ $participant->institution }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Email Address</dt>
                <dd class="font-medium text-gray-900">{{ $participant->email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Attendance Date</dt>
                <dd class="font-medium text-gray-900">{{ $participant->formattedAttendanceDates('d F Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Waktu Submit</dt>
                <dd class="font-medium text-gray-900">{{ DateTimeFormatter::display($participant->submitted_at) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Kode Referensi</dt>
                <dd class="font-mono font-medium text-gray-900">{{ $participant->referenceCode() }}</dd>
            </div>
        </dl>

        <div class="mt-6 border-t border-gray-100 pt-4">
            <h3 class="text-sm font-medium text-gray-700">Tanda Tangan</h3>
            @if ($signatureUrl)
                <img src="{{ $signatureUrl }}" alt="Tanda tangan {{ $participant->full_name }}" class="mt-3 max-h-40 rounded border border-gray-200 bg-white p-2">
            @else
                <p class="mt-2 text-sm text-gray-500">Tidak tersedia</p>
            @endif
        </div>
    </x-card>
@endsection
