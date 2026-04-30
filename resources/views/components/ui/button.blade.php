@props([
    'variant' => 'primary',  // primary | secondary | ghost | danger
    'size'    => 'md',       // sm | md | lg
    'type'    => 'button',
    'as'      => 'button',   // button | a
    'href'    => null,
    'icon'    => null,
    'iconOnly'=> false,
])

@php
    $variantCls = [
        'primary'   => 'btn-primary',
        'secondary' => 'btn-secondary',
        'ghost'     => 'btn-ghost',
        'danger'    => 'btn-danger',
        'success'   => 'btn-primary',
    ][$variant] ?? 'btn-primary';

    $sizeCls = ['sm' => 'btn-sm', 'lg' => 'btn-lg', 'md' => ''][$size] ?? '';
    $iconCls = $iconOnly ? 'btn-icon' : '';
    $classes = trim("btn {$variantCls} {$sizeCls} {$iconCls}");
@endphp

@if($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<span class="inline-flex h-4 w-4">{!! $icon !!}</span>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<span class="inline-flex h-4 w-4">{!! $icon !!}</span>@endif
        {{ $slot }}
    </button>
@endif
