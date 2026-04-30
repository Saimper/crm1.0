@props([
    'label' => '',
    'for'   => null,
    'hint'  => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->merge(['class' => 'field']) }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="field-label">
            {{ $label }}
            @if($required)<span style="color: var(--danger);" aria-hidden="true"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($hint && ! $error)
        <p class="field-help">{{ $hint }}</p>
    @endif

    @if($error)
        <p class="field-error">{{ $error }}</p>
    @endif
</div>
