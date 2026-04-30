@props([
    'initials' => '',
    'size'     => 'md',  // sm | md | lg
    'tone'     => 'brand',  // brand | neutral
])

@php
    $sizeCls = ['sm' => '', 'md' => 'avatar-md', 'lg' => 'avatar-lg'][$size] ?? '';
    $style = $tone === 'neutral'
        ? 'background: var(--bg-subtle); color: var(--text-secondary); border-color: var(--border);'
        : '';
@endphp

<span {{ $attributes->merge(['class' => "avatar {$sizeCls}"]) }} @if($style) style="{{ $style }}" @endif>
    {{ $initials ?: $slot }}
</span>
