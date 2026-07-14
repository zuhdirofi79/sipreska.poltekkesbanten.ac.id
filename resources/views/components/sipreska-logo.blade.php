@props([
    'variant' => 'default',
    'alt' => 'Logo Poltekkes Kemenkes Banten',
])

@php
    $variantClasses = match ($variant) {
        'sidebar' => 'sipreska-logo sipreska-logo-sidebar',
        'login' => 'sipreska-logo sipreska-logo-login',
        'header' => 'sipreska-logo sipreska-logo-header',
        default => 'sipreska-logo sipreska-logo-default',
    };

    $fallbackClasses = match ($variant) {
        'sidebar' => 'sipreska-logo-fallback sipreska-logo-fallback-sidebar',
        'login' => 'sipreska-logo-fallback sipreska-logo-fallback-login',
        'header' => 'sipreska-logo-fallback sipreska-logo-fallback-header',
        default => 'sipreska-logo-fallback sipreska-logo-fallback-default',
    };

    $logoExists = \App\Support\SipreskaBranding::logoExists();
    $logoUrl = \App\Support\SipreskaBranding::logoUrl();
@endphp

<div {{ $attributes->class(['sipreska-logo-wrap']) }}>
    @if ($logoExists)
        <img
            src="{{ $logoUrl }}"
            alt="{{ $alt }}"
            class="{{ $variantClasses }}"
            loading="lazy"
            decoding="async"
            onerror="this.hidden=true; this.nextElementSibling.hidden=false;"
        >
    @endif

    <div @class([$fallbackClasses, 'hidden' => $logoExists]) @if ($logoExists) hidden @endif aria-hidden="true">
        <svg class="sipreska-logo-fallback-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 9h.01M15 9h.01M9 13h.01M15 13h.01"/>
        </svg>
    </div>
</div>
