@props([])

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }}>
    {{ $slot }}
</div>
