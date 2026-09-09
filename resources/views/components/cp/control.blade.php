@props([
    'campo',              // fila de campos_personalizados
    'model',              // ruta del wire:model, p.ej. "valoresCamposCaso.saldo_total"
    'opciones' => null,   // colección de opciones_campo_personalizado, sólo para selección
    'disabled' => false,
    'clase' => null,      // las dos familias de estilo que conviven en el repo: `input` o utilidades
])

@php
    /**
     * El control que le toca a un campo personalizado por su tipo.
     *
     * Este componente existe porque el mismo `@switch` estaba copiado en seis
     * blades con tres criterios distintos: en unos `moneda` era `type=text`, en
     * otros `type=number step=0.01`; en unos `booleano` era un `<select>`, en
     * otros un checkbox. Y `seleccion_unica` y `seleccion_multiple` no estaban
     * en ninguno: caían en el `@default`, se escribían a mano y se guardaban
     * como `0`.
     *
     * Regla que no se rompe: la máscara vive en la presentación y el modelo
     * lleva el valor crudo. `<input type="date">` ya hace exactamente eso —el
     * navegador lo pinta en el formato del usuario y bindea ISO—, así que no
     * hace falta librería. Un picker que bindease «05/12/2026» guardaría 12 de
     * mayo sin dar error.
     */
    $tipo = (string) $campo->tipo;
    // Dos familias de estilo conviven en el repo: la clase semántica `.input`
    // de las pantallas de administración y las utilidades de las operativas.
    // El componente no elige por el llamante; sólo pone el control correcto.
    $base = $clase ?? 'mt-1 block w-full text-sm rounded border-ink-300 focus:border-brand-500 focus:ring-brand-500';
    $numerico = $clase === 'input' ? 'input mono' : $base.' text-right font-mono';
    $attrs = $disabled ? ['disabled' => true] : [];
@endphp

@switch($tipo)
    @case('texto_largo')
        <textarea wire:model="{{ $model }}" rows="2" @disabled($disabled)
                  class="{{ $base }}"></textarea>
        @break

    @case('numero_entero')
        <input type="number" step="1" inputmode="numeric" wire:model="{{ $model }}" @disabled($disabled)
               class="{{ $numerico }}"/>
        @break

    @case('numero_decimal')
        <input type="number" step="0.01" inputmode="decimal" wire:model="{{ $model }}" @disabled($disabled)
               placeholder="0.00" class="{{ $numerico }}"/>
        @break

    @case('moneda')
        {{-- El símbolo va fuera del input: dentro contaminaría el valor que se
             guarda y el validador de decimal lo rechazaría. --}}
        <div class="mt-1 flex items-center gap-1">
            <span class="text-xs text-ink-500">{{ __('casos.currency_symbol') }}</span>
            <input type="number" step="0.01" inputmode="decimal" wire:model="{{ $model }}" @disabled($disabled)
                   placeholder="0.00" class="{{ $numerico }}"/>
        </div>
        @break

    @case('fecha')
        <input type="date" wire:model="{{ $model }}" @disabled($disabled) class="{{ $base }}"/>
        @break

    @case('fecha_hora')
        <input type="datetime-local" wire:model="{{ $model }}" @disabled($disabled) class="{{ $base }}"/>
        @break

    @case('booleano')
        <label class="mt-1 inline-flex items-center gap-2">
            <input type="checkbox" wire:model="{{ $model }}" @disabled($disabled)
                   class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"/>
            <span class="text-xs text-ink-600">{{ __('casos.yes') }}</span>
        </label>
        @break

    @case('seleccion_unica')
        <select wire:model="{{ $model }}" @disabled($disabled) class="{{ $base }}">
            <option value="">—</option>
            @foreach($opciones ?? [] as $opcion)
                <option value="{{ $opcion->id }}">{{ $opcion->etiqueta }}</option>
            @endforeach
        </select>
        @break

    @case('seleccion_multiple')
        <select wire:model="{{ $model }}" multiple size="4" @disabled($disabled) class="{{ $base }}">
            @foreach($opciones ?? [] as $opcion)
                <option value="{{ $opcion->id }}">{{ $opcion->etiqueta }}</option>
            @endforeach
        </select>
        @break

    @default
        <input type="text" wire:model="{{ $model }}" @disabled($disabled) class="{{ $base }}"/>
@endswitch
