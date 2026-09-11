@props([])

{{-- Lista de gestiones: cada hija es un <x-ui.timeline-item>. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col']) }}>
    {{ $slot }}
</div>
