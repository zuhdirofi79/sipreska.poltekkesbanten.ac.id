@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'sipreska-input']) }}
    >
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
