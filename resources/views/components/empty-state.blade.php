@props([
    'title' => 'Belum ada data',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"/>
        </svg>
    </div>
    <h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    @if (isset($action))
        <div class="mt-4">
            {{ $action }}
        </div>
    @endif
</div>
