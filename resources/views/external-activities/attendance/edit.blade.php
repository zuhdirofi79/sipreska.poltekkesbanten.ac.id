@extends('layouts.app')

@section('title', 'Koreksi Peserta Eksternal')
@section('page_title', 'Koreksi Data Peserta')
@section('page_description', $participant->full_name)

@section('content')
    <nav class="mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('external-activities.attendance.index', $activity) }}" class="hover:text-blue-600">Daftar Hadir</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-medium text-gray-900">Koreksi</li>
        </ol>
    </nav>

    <x-page-header title="Koreksi Admin" description="Perubahan hanya dapat dilakukan oleh admin. Peserta tidak dapat mengubah data setelah submit." />

    @php
        $selectedDates = collect(old('attendance_dates', $participant->attendanceDates->map(fn ($date) => $date->event_date?->format('Y-m-d'))->all()));
    @endphp

    <form method="POST" action="{{ route('external-activities.attendance.update', [$activity, $participant]) }}">
        @csrf
        @method('PUT')
        <x-card>
            <div class="grid gap-4 md:grid-cols-2">
                <x-form-input label="Full Name" name="full_name" :value="$participant->full_name" required />
                <x-form-input label="Institution" name="institution" :value="$participant->institution" required />
                <div class="md:col-span-2">
                    <x-form-input label="Email Address" name="email" type="email" :value="$participant->email" required />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">Attendance Date <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                        @foreach ($activity->dates as $date)
                            @php $value = $date->event_date?->format('Y-m-d'); @endphp
                            <label class="flex items-center gap-2 text-sm text-gray-800">
                                <input
                                    type="checkbox"
                                    name="attendance_dates[]"
                                    value="{{ $value }}"
                                    class="rounded border-gray-300"
                                    @checked($selectedDates->contains($value))
                                >
                                {{ $date->event_date?->translatedFormat('d F Y') }}
                            </label>
                        @endforeach
                    </div>
                    @error('attendance_dates')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                <button type="submit" class="sipreska-btn-primary">Simpan Koreksi</button>
                <a href="{{ route('external-activities.attendance.show', [$activity, $participant]) }}" class="sipreska-btn-secondary">Batal</a>
            </div>
        </x-card>
    </form>
@endsection
