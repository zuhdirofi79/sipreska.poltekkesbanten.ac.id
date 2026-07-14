@props(['align' => 'left'])

<th {{ $attributes->merge(['class' => 'px-4 py-3 text-' . $align]) }}>
    {{ $slot }}
</th>
