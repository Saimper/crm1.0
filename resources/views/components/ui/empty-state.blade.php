@props([
    'title'   => 'Sin datos',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'empty']) }}>
    @isset($icon)
        <div class="empty-icon">{!! $icon !!}</div>
    @endisset
    <h3 class="empty-title">{{ $title }}</h3>
    @if($message)
        <p class="empty-desc">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4 flex items-center justify-center">{{ $action }}</div>
    @endisset
</div>
