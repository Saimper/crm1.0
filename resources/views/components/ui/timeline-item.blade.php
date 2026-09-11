@props([
    'tone'      => 'neutral',   // neutral | success | warning | danger | info | primary
    'timestamp' => null,
    'title'     => null,        // texto plano, o un <x-slot:title> con marcado
])

@php
    // Nombres literales: Tailwind sólo conserva las clases que encuentra
    // escritas en una plantilla, y `tl-dot-{{ $tone }}` no lo encontraría.
    $punto = match ($tone) {
        'success' => 'tl-dot tl-dot-success',
        'warning' => 'tl-dot tl-dot-warning',
        'danger'  => 'tl-dot tl-dot-danger',
        'info'    => 'tl-dot tl-dot-info',
        'primary' => 'tl-dot tl-dot-primary',
        default   => 'tl-dot',
    };
@endphp

{{-- Punto | texto | hora. El título va en negrita y admite un slot para
     colorear su segunda mitad («Promesa de pago · Llamada saliente»). --}}
<div {{ $attributes->merge(['class' => 'tl-item']) }}>
    <div class="tl-rail"><span class="{{ $punto }}"></span></div>
    <div class="min-w-0">
        @if($title)<div class="tl-title">{{ $title }}</div>@endif
        {{ $slot }}
    </div>
    @if($timestamp)<span class="tl-time">{{ $timestamp }}</span>@endif
</div>
