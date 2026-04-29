@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'etapa del embudo',
    'items'    => $items,
    'cabecerasExtra' => fn (): string =>
        '<th class="px-3 py-2 text-right">Nivel</th>'
       .'<th class="px-3 py-2 text-right">Prob. cierre</th>',
    'filasExtra'     => fn (object $it): string =>
        '<td class="px-3 py-2 text-right font-mono text-xs">'.$it->nivel.'</td>'
       .'<td class="px-3 py-2 text-right font-mono text-xs">'.$it->probabilidad_cierre.'%</td>',
    'camposExtra' => fn (): string => <<<'HTML'
        <div>
            <label class="block text-xs font-medium text-gray-700">Nivel (1-99, único)</label>
            <input type="number" min="1" max="99" wire:model="form.nivel"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Probabilidad de cierre (0-100%)</label>
            <input type="number" min="0" max="100" wire:model="form.probabilidad_cierre"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
HTML,
])
