@props([
    'empty' => null,
])

<div {{ $attributes->merge(['class' => 'sipreska-card overflow-x-auto']) }}>
    <table class="min-w-full text-sm">
        @if (isset($head))
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-600">
                {{ $head }}
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
    @if ($empty)
        <x-empty-state :title="$empty" />
    @endif
</div>
