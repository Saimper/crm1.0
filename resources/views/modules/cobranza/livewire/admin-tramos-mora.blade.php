@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'tramo de mora',
    'items'    => $items,
    'cabecerasExtra' => fn (): string =>
        '<th class="px-3 py-2 text-right">Desde</th>'
       .'<th class="px-3 py-2 text-right">Hasta</th>',
    'filasExtra'     => fn (object $it): string =>
        '<td class="px-3 py-2 text-right font-mono text-xs">'.$it->dias_desde.'</td>'
       .'<td class="px-3 py-2 text-right font-mono text-xs">'.($it->dias_hasta !== null ? $it->dias_hasta : '∞').'</td>',
    'camposExtra' => fn (): string => <<<'HTML'
        <div>
            <label class="block text-xs font-medium text-gray-700">Días desde</label>
            <input type="number" min="0" wire:model="form.dias_desde"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Días hasta (vacío = sin límite)</label>
            <input type="number" min="0" wire:model="form.dias_hasta"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
HTML,
])
