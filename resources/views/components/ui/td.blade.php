@props([
    'align' => 'left',
    'mono'  => false,
    'num'   => false,
])

@php
    $extra = [];
    if ($align === 'right') { $extra[] = 'text-right'; }
    if ($align === 'center') { $extra[] = 'text-center'; }
    if ($mono) { $extra[] = 'font-mono'; }
    if ($num) { $extra[] = 'num'; }
@endphp

<td {{ $attributes->merge(['class' => implode(' ', $extra)]) }}>
    {{ $slot }}
</td>
