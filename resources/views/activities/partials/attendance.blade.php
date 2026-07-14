<x-card title="Daftar Hadir / Absensi">
    @php
        $form = $activity->attendanceForm;
        $responseCount = $form?->responses()->count() ?? 0;
        $routesReady = Route::has('activities.daftar-hadir.store')
            && Route::has('activities.daftar-hadir.qr')
            && Route::has('activities.daftar-hadir.responses')
            && Route::has('activities.daftar-hadir.export-excel')
            && Route::has('activities.daftar-hadir.export-pdf')
            && Route::has('activities.daftar-hadir.print')
            && Route::has('attendance.public.show');
    @endphp

    @unless ($routesReady)
        @include('activities.partials.route-unavailable', ['feature' => 'Daftar Hadir / Absensi'])
    @else

    @if ($form)
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs text-gray-500">Status Form</div>
                <div class="mt-1 font-semibold text-gray-900">
                    @if ($form->isAcceptingSubmissions())
                        <x-badge variant="green">Form aktif</x-badge>
                    @else
                        <x-badge variant="gray">Form ditutup</x-badge>
                    @endif
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs text-gray-500">Jumlah Respons</div>
                <div class="mt-1 font-semibold text-gray-900">{{ $responseCount }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs text-gray-500">Tanda Tangan</div>
                <div class="mt-1 font-semibold text-gray-900">{{ $form->signature_required ? 'Wajib' : 'Opsional' }}</div>
            </div>
        </div>

        @if (isset($publicAttendanceUrl))
        @php
            $publicUrl = $publicAttendanceUrl;
            $whatsappUrl = 'https://wa.me/?text=' . rawurlencode(
                'Silakan isi daftar hadir kegiatan ' . $activity->title . ' melalui tautan berikut: ' . $publicUrl
            );
            $qrImageUrl = route('activities.daftar-hadir.qr', $activity);
            $isClosedByTime = $form->is_open && $form->closes_at && now()->gt($form->closes_at);
            $isNotYetOpen = $form->is_open && $form->opens_at && now()->lt($form->opens_at);

            if (! $form->is_open) {
                $shareSubtitle = 'Form absensi belum aktif. Aktifkan form terlebih dahulu sebelum membagikannya kepada peserta.';
            } elseif ($form->isAcceptingSubmissions()) {
                $shareSubtitle = 'Form absensi sudah aktif. Silakan bagikan tautan berikut kepada peserta kegiatan.';
            } elseif ($isClosedByTime) {
                $shareSubtitle = 'Form absensi sudah ditutup. Tautan masih dapat disalin untuk arsip atau pratinjau.';
            } elseif ($isNotYetOpen) {
                $shareSubtitle = 'Form absensi sudah diaktifkan tetapi belum dibuka sesuai jadwal. Anda dapat membagikan tautan sebelum waktu pembukaan.';
            } else {
                $shareSubtitle = 'Form absensi sudah aktif. Silakan bagikan tautan berikut kepada peserta kegiatan.';
            }
        @endphp

        <div
            class="mb-4 rounded-xl border border-blue-100 bg-blue-50/60 p-4 md:p-5"
            data-attendance-share-panel
            data-public-url="{{ $publicUrl }}"
            data-qr-image-url="{{ $qrImageUrl }}"
            data-whatsapp-url="{{ $whatsappUrl }}"
            x-data="attendanceSharePanel(@js([
                'publicUrl' => $publicUrl,
                'activityTitle' => $activity->title,
                'isOpen' => (bool) $form->is_open,
                'isAccepting' => $form->isAcceptingSubmissions(),
                'isClosedByTime' => $isClosedByTime,
                'isNotYetOpen' => $isNotYetOpen,
                'qrImageUrl' => $qrImageUrl,
                'whatsappUrl' => $whatsappUrl,
            ]))"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-blue-950">Bagikan Form Absensi</h3>
                    <p class="mt-1 text-sm text-blue-900">{{ $shareSubtitle }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($form->isAcceptingSubmissions())
                        <x-badge variant="green">Form aktif</x-badge>
                    @elseif ($form->is_open && $form->closes_at && now()->gt($form->closes_at))
                        <x-badge variant="gray">Form ditutup</x-badge>
                    @elseif (! $form->is_open)
                        <x-badge variant="gray">Belum aktif</x-badge>
                    @elseif ($form->opens_at && now()->lt($form->opens_at))
                        <x-badge variant="gray">Belum dibuka</x-badge>
                    @else
                        <x-badge variant="gray">Form ditutup</x-badge>
                    @endif
                </div>
            </div>

            <p class="mt-3 text-sm text-blue-800">
                Silakan salin tautan untuk dibagikan melalui WhatsApp, email, poster, atau layar presentasi.
            </p>

            <div class="mt-4">
                <label for="attendance-public-url" class="mb-1 block text-sm font-medium text-blue-950">
                    Tautan Publik Absensi
                </label>
                <input
                    id="attendance-public-url"
                    type="text"
                    readonly
                    x-ref="publicUrlInput"
                    value="{{ $publicUrl }}"
                    :value="publicUrl"
                    class="sipreska-input w-full min-w-0 truncate bg-white text-sm text-gray-700"
                    aria-label="Tautan publik absensi"
                >
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <button
                    type="button"
                    class="sipreska-btn-primary w-full sm:w-auto"
                    :class="{ 'bg-green-600 hover:bg-green-600': copied }"
                    :disabled="copyDisabled"
                    @click="copyLink()"
                    :aria-label="copied ? 'Link berhasil disalin' : 'Salin link absensi'"
                >
                    <span x-show="!copied">Salin Link</span>
                    <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Tersalin
                    </span>
                </button>

                <a
                    href="{{ $publicUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="sipreska-btn-secondary w-full sm:w-auto"
                    aria-label="Pratinjau form absensi publik"
                >
                    Pratinjau Form
                </a>

               

                <a
                    href="{{ $whatsappUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="sipreska-btn-secondary w-full sm:w-auto @if (! $form->is_open) pointer-events-none opacity-50 @endif"
                    @if (! $form->is_open) tabindex="-1" aria-disabled="true" @endif
                    aria-label="Bagikan tautan absensi ke WhatsApp"
                >
                    Bagikan ke WhatsApp
                </a>
            </div>

            <p class="mt-3 text-xs text-blue-700" x-show="copyDisabled" x-cloak>
                Aktifkan form terlebih dahulu.
            </p>

            <p class="mt-3 text-xs text-blue-700">
                Peserta dapat membuka tautan ini tanpa login dan mengisi daftar hadir seperti Google Form.
            </p>

            <p class="mt-1 text-xs text-blue-600">
                Gunakan Pratinjau untuk memastikan form dapat diakses sebelum dibagikan.
            </p>

            <div
                x-show="toast"
                x-cloak
                x-transition.opacity
                class="fixed bottom-4 right-4 z-[60] max-w-sm rounded-xl border px-4 py-3 text-sm shadow-lg"
                :class="{
                    'border-green-200 bg-green-50 text-green-800': toast?.type === 'success',
                    'border-red-200 bg-red-50 text-red-800': toast?.type === 'error',
                    'border-blue-200 bg-blue-50 text-blue-800': toast?.type === 'info',
                }"
                role="status"
                aria-live="polite"
                x-text="toast?.message"
            ></div>

            <div
                x-show="qrOpen"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @keydown.escape.window="closeQrModal()"
            >
                <div
                    x-show="qrOpen"
                    x-transition
                    @click.outside="closeQrModal()"
                    class="sipreska-card w-full max-w-md p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="qr-modal-title"
                >
                    <h4 id="qr-modal-title" class="text-base font-semibold text-gray-900">{{ $activity->title }}</h4>
                    <p class="mt-1 text-sm text-gray-500">Silakan buka tautan berikut untuk mengisi daftar hadir</p>

                    <div class="mt-4 flex justify-center rounded-lg border border-gray-100 bg-white p-4">
                        <img
                            src="{{ $qrImageUrl }}"
                            :src="qrImageUrl"
                            alt="QR Code absensi {{ $activity->title }}"
                            class="h-56 w-56 max-w-full object-contain"
                            width="224"
                            height="224"
                        >
                    </div>

                    <p class="mt-3 break-all text-center text-xs text-gray-500" x-text="publicUrl"></p>

                    <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                        <button type="button" class="sipreska-btn-secondary w-full sm:w-auto" @click="downloadQr()">
                            Download PNG
                        </button>
                        <button type="button" class="sipreska-btn-secondary w-full sm:w-auto" @click="printQr()">
                            Cetak
                        </button>
                        <button type="button" class="sipreska-btn-primary w-full sm:w-auto" @click="closeQrModal()">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @else
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Tautan absensi belum dapat dibuat. Hubungi administrator.
            </div>
        @endif
    @else
        <x-empty-state title="Form presensi belum dibuat" description="Form absensi akan dibuat otomatis saat kegiatan disimpan." />
    @endif

    @can('manageAttendance', $activity)
        <form method="POST" action="{{ route('activities.daftar-hadir.store', $activity) }}" class="mt-4 space-y-4 border-t border-gray-100 pt-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Buka Form Presensi</label>
                    <select name="is_open" class="sipreska-select">
                        <option value="1" @selected(old('is_open', $form?->is_open))>Dibuka</option>
                        <option value="0" @selected(old('is_open', $form?->is_open) === false)>Ditutup</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Tanda Tangan Wajib</label>
                    <select name="signature_required" class="sipreska-select">
                        <option value="1" @selected(old('signature_required', $form?->signature_required ?? true))>Ya</option>
                        <option value="0" @selected(old('signature_required', $form?->signature_required) === false)>Tidak</option>
                    </select>
                </div>
                <x-form-input label="Dibuka Mulai" name="opens_at" type="datetime-local" :value="old('opens_at', $form?->opens_at?->format('Y-m-d\TH:i'))" />
                <x-form-input label="Ditutup Pada" name="closes_at" type="datetime-local" :value="old('closes_at', $form?->closes_at?->format('Y-m-d\TH:i'))" />
            </div>
            <button type="submit" class="sipreska-btn-primary">{{ $form ? 'Perbarui' : 'Buat' }} Form Presensi</button>
        </form>

        @if ($form)
            @php
                $lastSubmittedAt = $form->responses()->max('submitted_at');
                $lastSubmittedDisplay = $lastSubmittedAt
                    ? \App\Support\DateTimeFormatter::display(\Illuminate\Support\Carbon::parse($lastSubmittedAt))
                    : '-';
                $exportDisabled = $responseCount === 0;
            @endphp

            <div class="mt-6 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 md:p-5">
                <h3 class="text-base font-semibold text-emerald-950">Partisipan yang Telah Hadir</h3>
                <p class="mt-1 text-sm text-emerald-900">
                    Lihat siapa saja yang telah mengisi form absensi beserta waktu kehadirannya.
                </p>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-emerald-700">Total Respons</dt>
                        <dd class="font-semibold text-emerald-950">{{ $responseCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-emerald-700">Respons Terakhir</dt>
                        <dd class="font-semibold text-emerald-950">{{ $lastSubmittedDisplay }}</dd>
                    </div>
                </dl>

                <p class="mt-3 text-xs text-emerald-800">
                    Lihat siapa saja yang telah mengisi form absensi dan waktu kehadirannya.
                </p>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('activities.daftar-hadir.responses', $activity) }}" class="sipreska-btn-primary w-full sm:w-auto">
                        Lihat Partisipan Hadir
                    </a>
                    <a
                        href="{{ route('activities.daftar-hadir.export-excel', $activity) }}"
                        class="sipreska-btn-secondary w-full sm:w-auto @if ($exportDisabled) pointer-events-none opacity-50 @endif"
                        @if ($exportDisabled) aria-disabled="true" tabindex="-1" @endif
                    >Export Excel</a>
                    <a
                        href="{{ route('activities.daftar-hadir.export-pdf', $activity) }}"
                        class="sipreska-btn-secondary w-full sm:w-auto @if ($exportDisabled) pointer-events-none opacity-50 @endif"
                        @if ($exportDisabled) aria-disabled="true" tabindex="-1" @endif
                    >Export PDF</a>
                    <a
                        href="{{ route('activities.daftar-hadir.print', $activity) }}"
                        target="_blank"
                        rel="noopener"
                        class="sipreska-btn-secondary w-full sm:w-auto @if ($exportDisabled) pointer-events-none opacity-50 @endif"
                        @if ($exportDisabled) aria-disabled="true" tabindex="-1" @endif
                    >Print</a>
                </div>
            </div>
        @endif
    @endcan
    @endunless
</x-card>
