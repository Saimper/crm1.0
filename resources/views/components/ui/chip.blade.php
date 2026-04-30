@props([
    'active' => false,
    'as'     => 'span',  // span | button | a
    'href'   => null,
])

@php
    $cls = 'chip'.($active ? ' active' : '');
@endphp

@if($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@elseif($as === 'button')
    <button type="button" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@else
    <span {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</span>
@endif
