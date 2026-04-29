@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'nivel SLA',
    'items'    => $items,
    'cabecerasExtra' => fn (): string => '<th class="px-3 py-2 text-right">Horas</th>',
    'filasExtra'     => fn (object $it): string =>
        '<td class="px-3 py-2 text-right font-mono text-xs">'.$it->horas_resolucion.' h</td>',
    'camposExtra' => fn (): string => <<<'HTML'
        <div>
            <label class="block text-xs font-medium text-gray-700">Horas de resolución</label>
            <input type="number" min="1" wire:model="form.horas_resolucion"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
        <div></div>
HTML,
])
