@props([
    'tone'  => 'info',  // info | success | warning | danger
    'title' => null,
])

@php
    $cls = "alert alert-{$tone}";
@endphp

<div {{ $attributes->merge(['class' => $cls]) }}>
    <div class="flex-1">
        @if($title)
            <div class="font-semibold">{{ $title }}</div>
        @endif
        <div>{{ $slot }}</div>
    </div>
    @isset($actions)
        <div class="alert-actions">{{ $actions }}</div>
    @endisset
</div>
