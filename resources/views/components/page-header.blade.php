@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-start justify-between gap-4']) }}>
    <div>
        <h2 class="text-xl font-semibold text-gray-900">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
