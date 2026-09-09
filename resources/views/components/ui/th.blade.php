@props([
    'align'  => 'left',   // left | right | center
    'num'    => false,
    'sort'   => null,     // clave de orden; si viene, la cabecera es un botón
    'activo' => null,     // clave por la que se está ordenando ahora
    'dir'    => 'asc',    // dirección actual
    'accion' => 'ordenarPor', // método Livewire que recibe la clave
])

@php
    $extra = [];
    if ($align === 'right') { $extra[] = 'text-right'; }
    if ($align === 'center') { $extra[] = 'text-center'; }
    if ($num) { $extra[] = 'num'; }

    $esActiva = $sort !== null && $sort === $activo;
@endphp

<th {{ $attributes->merge(['class' => implode(' ', $extra)]) }}
    @if($esActiva) aria-sort="{{ $dir === 'desc' ? 'descending' : 'ascending' }}" @endif>
    @if($sort === null)
        {{ $slot }}
    @else
        {{-- La clave viaja al servidor y allí se contrasta contra el catálogo de
             columnas: nunca se interpola en el ORDER BY tal como llega. --}}
        <button type="button" class="th-sort" wire:click="{{ $accion }}('{{ $sort }}')">
            {{ $slot }}
            <span class="th-sort-mark {{ $esActiva ? 'is-active' : '' }}">
                {{ $esActiva ? ($dir === 'desc' ? '▼' : '▲') : '↕' }}
            </span>
        </button>
    @endif
</th>
