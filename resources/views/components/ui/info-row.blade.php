@props([
    'label' => '',
    'mono'  => false,
    'accent'=> false,
])

@php
    $valueCls = $mono ? 'font-mono' : '';
    $valueStyle = $accent
        ? 'font-size: 16px; font-weight: 600; color: var(--text);'
        : 'font-size: 13px; color: var(--text);';
@endphp

<div>
    <dt class="label-xs" style="font-size: 11px; margin-bottom: 3px;">{{ $label }}</dt>
    <dd class="{{ $valueCls }}" style="{{ $valueStyle }}">{{ $slot }}</dd>
</div>
