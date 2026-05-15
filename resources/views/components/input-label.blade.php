@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-ink-700']) }}>
    {{ $value ?? $slot }}
</label>
