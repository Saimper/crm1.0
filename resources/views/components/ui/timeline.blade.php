@props([])

<div {{ $attributes->merge(['class' => 'flex flex-col']) }} style="padding: 8px 6px;">
    {{ $slot }}
</div>
