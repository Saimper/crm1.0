@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'nivel de escalamiento',
    'items'    => $items,
    'cabecerasExtra' => fn (): string => '<th class="px-3 py-2 text-right">Nivel</th>',
    'filasExtra'     => fn (object $it): string =>
        '<td class="px-3 py-2 text-right font-mono text-xs">N'.$it->nivel.'</td>',
    'camposExtra' => fn (): string => <<<'HTML'
        <div>
            <label class="block text-xs font-medium text-gray-700">Nivel (1-99, único)</label>
            <input type="number" min="1" max="99" wire:model="form.nivel"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
        <div></div>
HTML,
])
