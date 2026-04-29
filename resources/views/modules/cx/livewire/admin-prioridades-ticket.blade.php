@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'prioridad de ticket',
    'items'    => $items,
    'cabecerasExtra' => fn (): string => '<th class="px-3 py-2 text-right">Peso</th>',
    'filasExtra'     => fn (object $it): string =>
        '<td class="px-3 py-2 text-right font-mono text-xs">'.$it->peso.'</td>',
    'camposExtra' => fn (): string => <<<'HTML'
        <div>
            <label class="block text-xs font-medium text-gray-700">Peso (mayor = más prioritaria)</label>
            <input type="number" min="0" wire:model="form.peso"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
        <div></div>
HTML,
])
