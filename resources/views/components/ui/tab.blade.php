@props([
    'active' => false,
    'count'  => null,
    'as'     => 'button',  // button | a
    'href'   => null,
])

@php
    $cls = 'tab'.($active ? ' active' : '');
@endphp

@if($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>
        {{ $slot }}
        @if($count !== null)<span class="count">{{ $count }}</span>@endif
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $cls]) }}>
        {{ $slot }}
        @if($count !== null)<span class="count">{{ $count }}</span>@endif
    </button>
@endif
