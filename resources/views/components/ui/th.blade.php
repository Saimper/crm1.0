@props([
    'align' => 'left',  // left | right | center
    'num'   => false,
])

@php
    $extra = [];
    if ($align === 'right') { $extra[] = 'text-right'; }
    if ($align === 'center') { $extra[] = 'text-center'; }
    if ($num) { $extra[] = 'num'; }
@endphp

<th {{ $attributes->merge(['class' => implode(' ', $extra)]) }}>
    {{ $slot }}
</th>
