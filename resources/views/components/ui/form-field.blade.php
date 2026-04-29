@props([
    'label' => '',
    'for'   => null,
    'hint'  => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->only('class') }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif
               class="block text-xs font-medium text-ink-700">
            {{ $label }}
            @if($required)<span class="text-danger-500" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="mt-1">
        {{ $slot }}
    </div>

    @if($hint && ! $error)
        <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
    @endif

    @if($error)
        <p class="mt-1 text-xs text-danger-600">{{ $error }}</p>
    @endif
</div>
