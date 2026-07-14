@props([
    'status',
])

@php
    $enum = $status instanceof \App\Enums\ActivityStatus
        ? $status
        : \App\Enums\ActivityStatus::tryFrom((string) $status);
@endphp

@if ($enum)
    <span {{ $attributes->merge(['class' => 'sipreska-badge ' . $enum->badgeClass()]) }}>
        {{ $enum->label() }}
    </span>
@else
    <x-badge {{ $attributes }} variant="gray">{{ $status }}</x-badge>
@endif
