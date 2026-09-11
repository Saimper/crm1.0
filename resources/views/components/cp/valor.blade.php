@props([
    'campo',            // fila de campos_personalizados
    'valor' => null,    // ya formateado por quien lo pinta
])

@php
    /**
     * Un campo personalizado en modo lectura: etiqueta, valor y «copiar».
     *
     * No es un input deshabilitado a propósito. Un input en gris sigue
     * pareciendo un formulario, invita a hacer clic y no deja copiar cómodo; y
     * con 98 campos, 98 inputs muertos siguen pesando en el DOM y en el payload
     * de cada round-trip de Livewire.
     */
    $texto = $valor === null || $valor === '' ? '—' : (string) $valor;
    $vacio = $texto === '—';
    $mono = in_array((string) $campo->tipo, ['numero_entero', 'numero_decimal', 'moneda', 'fecha', 'fecha_hora'], true);
@endphp

<div x-data="{ copiado: false }" class="cp-valor min-w-0">
    <div class="flex items-center justify-between gap-1.5 text-xs text-ink-500">
        <span class="truncate">{{ $campo->etiqueta }}</span>
        @unless($vacio)
            <button type="button"
                    class="cp-copiar shrink-0 text-xs text-brand-500 hover:text-brand-700"
                    x-on:click="navigator.clipboard.writeText(@js($texto)); copiado = true; setTimeout(() => copiado = false, 1200)"
                    :aria-label="copiado ? @js(__('common.copied')) : @js(__('common.copy'))">
                <span x-show="! copiado">{{ __('common.copy') }}</span>
                <span x-show="copiado" x-cloak class="text-success-700">{{ __('common.copied') }}</span>
            </button>
        @endunless
    </div>
    <div class="mt-0.5 text-base truncate {{ $vacio ? 'text-ink-400' : 'text-ink' }} {{ $mono ? 'font-mono' : '' }}"
         @unless($vacio) title="{{ $texto }}" @endunless>{{ $texto }}</div>
</div>
