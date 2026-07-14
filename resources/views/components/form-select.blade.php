@props([
    'label',
    'name',
    'required' => false,
    'placeholder' => null,
    'options' => [],
    'selected' => null,
])

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'sipreska-select']) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $selected) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
        {{ $slot }}
    </select>
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
