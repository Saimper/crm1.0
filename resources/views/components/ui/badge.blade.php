@props([
    'tone' => 'neutral',  // neutral | primary | success | warning | danger | info | brand | accent
    'size' => 'md',       // sm | md
    'dot'  => false,
])

@php
    $tone = match ($tone) {
        'brand'  => 'primary',
        'accent' => 'primary',
        default  => $tone,
    };
    $cls = "badge badge-{$tone}";
@endphp

<span {{ $attributes->merge(['class' => $cls]) }}>
    @if($dot)<span class="dot dot-{{ $tone }}"></span>@endif
    {{ $slot }}
</span>
