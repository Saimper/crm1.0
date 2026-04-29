@props([
    'variant' => 'primary',  // primary | secondary | ghost | danger | success
    'size'    => 'md',       // sm | md | lg
    'type'    => 'button',
    'as'      => 'button',   // button | a
    'href'    => null,
    'icon'    => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed';

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-4 py-2.5 text-sm',
    ][$size] ?? 'px-3.5 py-2 text-sm';

    $variants = [
        'primary'   => 'bg-brand-600 text-white hover:bg-brand-700 focus:ring-brand-500 shadow-sm',
        'secondary' => 'bg-white text-ink-700 border border-surface-border hover:bg-surface-50 focus:ring-brand-500',
        'ghost'     => 'text-ink-700 hover:bg-surface-100 focus:ring-surface-300',
        'danger'    => 'bg-danger-600 text-white hover:bg-danger-700 focus:ring-danger-500 shadow-sm',
        'success'   => 'bg-success-600 text-white hover:bg-success-700 focus:ring-success-500 shadow-sm',
    ][$variant] ?? 'bg-brand-600 text-white hover:bg-brand-700 focus:ring-brand-500 shadow-sm';

    $classes = "{$base} {$sizes} {$variants}";
@endphp

@if($as === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<span class="h-4 w-4">{!! $icon !!}</span>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<span class="h-4 w-4">{!! $icon !!}</span>@endif
        {{ $slot }}
    </button>
@endif
