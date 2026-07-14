@props([
    'title' => null,
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'sipreska-card' . ($padding ? ' p-6' : '')]) }}>
    @if ($title)
        <h3 class="mb-4 text-base font-semibold text-gray-900">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>
