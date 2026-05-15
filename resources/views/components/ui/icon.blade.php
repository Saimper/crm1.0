@props([
    'name'   => '',
    'size'   => 16,
    'stroke' => 1.5,
    'class'  => '',
])

@php
    // Set lucide-style copiado de Núcleo CRM (standalone).html — F29.
    // Stroke 1.5, viewBox 24, fill none, currentColor.
    $svgs = [
        'search'        => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bell'          => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left'  => '<path d="m15 6-6 6 6 6"/>',
        'chevron-up'    => '<path d="m6 15 6-6 6 6"/>',
        'plus'          => '<path d="M12 5v14M5 12h14"/>',
        'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
        'x-mark'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check'         => '<path d="M20 6 9 17l-5-5"/>',
        'filter'        => '<path d="M3 5h18l-7 9v6l-4-2v-4z"/>',
        'inbox'         => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
        'users'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'          => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'briefcase'     => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'folder'        => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
        'bar-chart'     => '<path d="M3 3v18h18"/><path d="M7 16V9M12 16v-5M17 16v-9"/>',
        'chart-bar'     => '<path d="M3 3v18h18"/><path d="M7 16V9M12 16v-5M17 16v-9"/>',
        'pie-chart'     => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.4.8.7 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'phone'         => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.86 19.86 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"/>',
        'mail'          => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/>',
        'message-square'=> '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'check-circle'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
        'alert-circle'  => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
        'alert-triangle'=> '<path d="m10.29 3.86-8.37 14a2 2 0 0 0 1.71 3h16.74a2 2 0 0 0 1.71-3l-8.37-14a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'info'          => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'arrow-up'      => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'arrow-down'    => '<path d="M12 5v14M19 12l-7 7-7-7"/>',
        'arrow-right'   => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'arrow-left'    => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'trending-up'   => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'database'      => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
        'file-text'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'clipboard'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'upload'        => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8 12 3 7 8M12 3v12"/>',
        'download'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
        'history'       => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'edit'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/>',
        'pencil'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'         => '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'building'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 22V12h6v10M9 6h.01M15 6h.01M9 10h.01M15 10h.01"/>',
        'shield'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'key'           => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
        'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'tag'           => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><path d="M7 7h.01"/>',
        'layers'        => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
        'log-out'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'more-horizontal' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'more-h'        => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'eye'           => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'refresh'       => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'map-pin'       => '<path d="M20 10c0 7-8 12-8 12s-8-5-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'wrench'        => '<path d="M14.7 6.3a4 4 0 1 0 4 6.6L21 21l-2 2-8-8.3a4 4 0 0 0-6.6-4l3 3-2 2-3-3a4 4 0 0 1 4-6.6Z"/>',
        'zap'           => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/>',
        'send'          => '<path d="m22 2-7 20-4-9-9-4 20-7Z"/>',
        'star'          => '<path d="m12 2 3.1 6.3 7 1-5 4.9 1.2 7L12 17.8 5.7 21.2l1.2-7-5-4.9 7-1Z"/>',
        'hash'          => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
    ];

    $logoSpecial = $name === 'logo';
    $body = $svgs[$name] ?? '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>';
    $extraClass = trim((string) $class);
@endphp

@if($logoSpecial)
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" {{ $attributes->merge(['class' => $extraClass]) }}>
        <circle cx="12" cy="12" r="10" fill="#2E75B6"/>
        <circle cx="12" cy="12" r="3.4" fill="#fff"/>
        <circle cx="12" cy="12" r="1.2" fill="#2E75B6"/>
    </svg>
@else
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round"
         {{ $attributes->merge(['class' => $extraClass]) }}>
        {!! $body !!}
    </svg>
@endif
