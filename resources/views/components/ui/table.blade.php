@props([
    'compact'   => false,
    'clickable' => false,
])

@php
    $cls = trim('table'.($compact ? ' table-compact' : '').($clickable ? ' table-clickable' : ''));
@endphp

<div {{ $attributes->merge(['class' => 'card overflow-x-auto']) }}>
    <table class="{{ $cls }}">
        @isset($head)
            <thead><tr>{{ $head }}</tr></thead>
        @endisset
        <tbody>{{ $slot }}</tbody>
    </table>
    @isset($footer)
        <div class="card-header" style="border-top: 1px solid var(--border); border-bottom: 0; background: var(--bg-subtle);">
            {{ $footer }}
        </div>
    @endisset
</div>
