@props([
    'target' => null,   // wire:target concreto; si no, cualquier petición del componente
])

{{--
    La barra de 2 px que dice que se está trabajando.

    Va pegada bajo la barra de filtros y NO vacía la pantalla: la lista anterior
    se queda visible mientras llega la nueva. Ocho de 178 vistas tenían algún
    `wire:loading`, así que filtrar parecía no hacer nada hasta que la tabla
    cambiaba sola.
--}}
<div wire:loading.delay @if($target) wire:target="{{ $target }}" @endif
     {{ $attributes->merge(['class' => 'loading-bar']) }}
     role="status" aria-label="{{ __('common.loading') }}"></div>
