@extends('layouts.public-attendance')

@section('title', 'Presensi - ' . $activity->title)

@section('content')
    <x-attendance-letterhead />

    <main class="public-attendance-form-wrapper">
        <div class="sipreska-card p-6 lg:p-8">
            <div class="mb-6 text-center">
                <p class="text-xs font-semibold uppercase tracking-wider text-purple-700">Form Daftar Hadir Kegiatan Eksternal</p>
                <h1 class="mt-2 text-xl font-bold text-gray-900">{{ $activity->title }}</h1>
                <p class="mt-2 text-sm text-gray-500">
                    {{ $activity->dates->map(fn ($date) => $date->event_date?->translatedFormat('d F Y'))->implode(' · ') }}
                </p>
            </div>

            @if ($duplicateSubmission ?? false)
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900">Absensi Sudah Tercatat</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Anda sudah mengisi daftar hadir untuk kegiatan ini.
                    </p>
                    <p class="mt-1 text-sm text-gray-500">Anda tidak perlu mengisi form kembali.</p>
                </div>
            @elseif (! $activity->isAcceptingSubmissions())
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Form presensi saat ini tidak menerima pengisian.
                </div>
            @else
                <form
                    method="POST"
                    action="{{ \App\Support\ExternalAttendancePublicRoutes::storeUrlForActivity($activity) }}"
                    x-ref="form"
                    x-data="externalAttendanceForm()"
                    @submit="handleFormSubmit($event)"
                    class="space-y-4"
                    data-external-attendance-form
                >
                    @csrf

                    <div class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <x-form-input label="Full Name" name="full_name" :value="old('full_name')" required />
                    <x-form-input label="Institution" name="institution" :value="old('institution')" required />
                    <x-form-input label="Email Address" name="email" type="email" :value="old('email')" required />

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            Attendance Date <span class="text-red-500">*</span>
                        </label>
                        <p class="mb-2 text-xs text-gray-500">Pilih satu atau lebih tanggal kehadiran.</p>
                        <div class="space-y-2">
                            @foreach ($activity->dates as $date)
                                @php $value = $date->event_date?->format('Y-m-d'); @endphp
                                <label class="flex items-center gap-2 text-sm text-gray-800">
                                    <input
                                        type="checkbox"
                                        name="attendance_dates[]"
                                        value="{{ $value }}"
                                        class="rounded border-gray-300"
                                        @checked(collect(old('attendance_dates', []))->contains($value))
                                    >
                                    {{ $date->event_date?->translatedFormat('d F Y') }}
                                </label>
                            @endforeach
                        </div>
                        @error('attendance_dates')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <p x-show="dateError" x-cloak class="mt-1 text-xs text-red-600" x-text="dateError"></p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tanda Tangan <span class="text-red-500">*</span></label>
                        <div class="rounded-xl border border-gray-200 bg-white p-2">
                            <canvas
                                x-ref="canvas"
                                class="sipreska-signature-canvas w-full"
                                width="600"
                                height="180"
                                @mousedown="startDraw($event)"
                                @mousemove="draw($event)"
                                @mouseup="endDraw()"
                                @mouseleave="endDraw()"
                                @touchstart.prevent="startDraw($event)"
                                @touchmove.prevent="draw($event)"
                                @touchend.prevent="endDraw()"
                            ></canvas>
                        </div>
                        <input type="hidden" name="signature_data" x-ref="signatureInput">
                        @error('signature_data')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p x-show="signatureError" x-cloak class="mt-1 text-xs text-red-600" x-text="signatureError"></p>
                        <button type="button" class="mt-2 text-sm text-gray-500 hover:text-gray-700" @click="clear()">Hapus tanda tangan</button>
                    </div>

                    <button
                        type="button"
                        x-ref="submitTrigger"
                        class="sipreska-btn-primary w-full"
                        :disabled="isSubmitting"
                        @click="requestOpenConfirm()"
                        x-text="isSubmitting ? 'Menyimpan...' : 'Kirim Presensi'"
                    >
                        Kirim Presensi
                    </button>

                    <button
                        type="submit"
                        x-ref="hiddenSubmit"
                        class="hidden"
                        tabindex="-1"
                        aria-hidden="true"
                        hidden
                    ></button>

                    <div
                        x-show="modalOpen"
                        x-cloak
                        x-transition.opacity
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        style="background-color: rgba(0, 0, 0, 0.45);"
                        @keydown.escape.window="handleEscape()"
                        @click.self="!isSubmitting && closeModal()"
                    >
                        <div
                            x-show="modalOpen"
                            x-transition
                            class="sipreska-card w-full p-6 shadow-lg"
                            style="max-width: min(520px, calc(100% - 32px)); border-radius: 14px;"
                            role="dialog"
                            aria-modal="true"
                            @click.stop
                        >
                            <h2 class="text-base font-semibold text-gray-900">Konfirmasi Simpan Presensi Kehadiran</h2>
                            <p class="mt-2 text-sm text-gray-600">Apakah Anda yakin menyimpan Presensi Kehadiran Anda?</p>
                            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                                <button
                                    type="button"
                                    class="sipreska-btn-primary order-1 w-full sm:order-2 sm:w-auto"
                                    x-ref="confirmYes"
                                    :disabled="isSubmitting"
                                    @click="confirmSubmit()"
                                    x-text="isSubmitting ? 'Menyimpan...' : 'Ya, Simpan'"
                                >
                                    Ya, Simpan
                                </button>
                                <button
                                    type="button"
                                    class="sipreska-btn-secondary order-2 w-full sm:order-1 sm:w-auto"
                                    :disabled="isSubmitting"
                                    @click="closeModal()"
                                >
                                    Tidak, Kembali
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </main>
@endsection
