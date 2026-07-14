@props([
    'variant' => 'gray',
])

@php
    $classes = match ($variant) {
        'blue' => 'sipreska-badge-blue',
        'green' => 'sipreska-badge-green',
        'gray' => 'sipreska-badge-gray',
        'purple' => 'sipreska-badge-purple',
        'red' => 'sipreska-badge-red',
        default => 'sipreska-badge-gray',
    };
@endphp

<span {{ $attributes->merge(['class' => "sipreska-badge {$classes}"]) }}>
    {{ $slot }}
</span>
