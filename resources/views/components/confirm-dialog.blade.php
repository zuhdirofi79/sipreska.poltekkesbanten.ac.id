@props([
    'id',
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin?',
    'confirmLabel' => 'Ya, lanjutkan',
    'cancelLabel' => 'Batal',
    'confirmClass' => 'sipreska-btn-primary',
])

<div
    x-data="{ open: false, form: null }"
    x-on:open-confirm.window="if ($event.detail?.id === '{{ $id }}') { open = true; form = $event.detail.form ?? null; }"
    x-cloak
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @keydown.escape.window="open = false"
    >
        <div
            x-show="open"
            x-transition
            @click.outside="open = false"
            class="sipreska-card w-full max-w-md p-6"
            role="dialog"
            aria-modal="true"
        >
            <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ $message }}</p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="sipreska-btn-secondary" @click="open = false">{{ $cancelLabel }}</button>
                <button
                    type="button"
                    class="{{ $confirmClass }}"
                    @click="if (form) { form.submit(); } else { $dispatch('confirmed', { id: '{{ $id }}' }); open = false; }"
                >
                    {{ $confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
