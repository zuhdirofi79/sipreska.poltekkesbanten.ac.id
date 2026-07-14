@props([
    'completeness',
])

@php
    $completed = $completeness['completed'] ?? 0;
    $total = $completeness['total'] ?? 0;
    $isComplete = $total > 0 && $completed === $total;
    $variant = $isComplete ? 'green' : ($completed > 0 ? 'blue' : 'gray');
@endphp

<x-badge {{ $attributes }} :variant="$variant" title="{{ implode(', ', $completeness['missing'] ?? []) }}">
    {{ $completeness['label'] ?? "{$completed}/{$total}" }}
</x-badge>
