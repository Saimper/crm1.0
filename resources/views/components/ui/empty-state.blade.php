@props([
    'title'   => 'Sin datos',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-surface-300 bg-surface-50 p-10 text-center']) }}>
    @isset($icon)
        <div class="mx-auto h-12 w-12 text-ink-400 mb-3">{!! $icon !!}</div>
    @endisset
    <h3 class="text-sm font-semibold text-ink-800">{{ $title }}</h3>
    @if($message)
        <p class="mt-1 text-sm text-ink-500 max-w-md mx-auto">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4 flex items-center justify-center">{{ $action }}</div>
    @endisset
</div>
