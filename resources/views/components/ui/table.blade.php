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
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
