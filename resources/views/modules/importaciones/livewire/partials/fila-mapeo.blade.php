<tr>
    <td class="px-3 py-2 align-top">
        <div class="flex items-center gap-2">
            <span class="font-medium text-ink-900">{{ $campo->etiqueta }}</span>
            @if($campo->requerido)
                <span class="rounded bg-danger-50 px-1.5 py-0.5 text-[10px] font-semibold text-danger-700">{{ __('importaciones.required_badge') }}</span>
            @else
                <span class="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] text-ink-600">{{ __('importaciones.optional_badge') }}</span>
            @endif
        </div>
        @if($campo->descripcion)
            <div class="text-[11px] text-ink-500 mt-0.5">{{ $campo->descripcion }}</div>
        @endif
        <div class="text-[10px] text-ink-400 mt-0.5 font-mono">{{ $campo->codigo }}</div>
    </td>
    <td class="px-3 py-2 align-top">
        <select wire:model="mapeo.{{ $campo->codigo }}"
                class="block w-full text-sm border-ink-300 rounded">
            <option value="">{{ __('importaciones.no_map_option') }}</option>
            @foreach($cabecerasCsv as $h)
                <option value="{{ $h }}">{{ $h }}</option>
            @endforeach
        </select>
        @error("mapeo.{$campo->codigo}")<div class="text-xs text-danger-600 mt-0.5">{{ $message }}</div>@enderror
    </td>
</tr>
