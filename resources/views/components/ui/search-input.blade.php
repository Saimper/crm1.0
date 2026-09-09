@props([
    'width' => '280px',
])

{{--
    El buscador con la lupa dentro, que hasta ahora era un `position:relative`
    con un `position:absolute` encima repetido en cada listado. Los atributos
    (wire:model, placeholder) van al input, que es lo que quien lo usa espera.
--}}
<div class="search-field" style="width: {{ $width }};">
    <span class="search-field-icon"><x-ui.icon name="search" :size="13" /></span>
    <input type="search" {{ $attributes->merge(['class' => 'input', 'aria-label' => $attributes->get('placeholder', __('common.search'))]) }} />
</div>
