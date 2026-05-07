<tr>
    <td class="px-3 py-2 align-top">
        <div class="flex items-center gap-2">
            <span class="font-medium text-gray-900">{{ $campo->etiqueta }}</span>
            @if($campo->requerido)
                <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">requerido</span>
            @else
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600">opcional</span>
            @endif
        </div>
        @if($campo->descripcion)
            <div class="text-[11px] text-gray-500 mt-0.5">{{ $campo->descripcion }}</div>
        @endif
        <div class="text-[10px] text-gray-400 mt-0.5 font-mono">{{ $campo->codigo }}</div>
    </td>
    <td class="px-3 py-2 align-top">
        <select wire:model="mapeo.{{ $campo->codigo }}"
                class="block w-full text-sm border-gray-300 rounded">
            <option value="">— No mapear —</option>
            @foreach($cabecerasCsv as $h)
                <option value="{{ $h }}">{{ $h }}</option>
            @endforeach
        </select>
        @error("mapeo.{$campo->codigo}")<div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>@enderror
    </td>
</tr>
