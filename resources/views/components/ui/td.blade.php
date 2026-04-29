@props([
    'align' => 'left',
    'mono'  => false,
])

@php
    $alignClass = [
        'left'   => 'text-left',
        'right'  => 'text-right',
        'center' => 'text-center',
    ][$align] ?? 'text-left';
    $monoClass = $mono ? 'font-mono' : '';
@endphp

<td {{ $attributes->merge(['class' => "px-4 py-2.5 text-sm text-ink-700 {$alignClass} {$monoClass}"]) }}>
    {{ $slot }}
</td>
