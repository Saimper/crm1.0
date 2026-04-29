@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'tipo de acción de servicio',
    'items'    => $items,
    'cabecerasExtra' => fn (): string => '<th class="px-3 py-2 text-right">Horas est.</th>',
    'filasExtra'     => fn (object $it): string =>
        '<td class="px-3 py-2 text-right font-mono text-xs">'.($it->duracion_estimada_horas !== null ? $it->duracion_estimada_horas.' h' : '—').'</td>',
    'camposExtra' => fn (): string => <<<'HTML'
        <div>
            <label class="block text-xs font-medium text-gray-700">Duración estimada (horas, opcional)</label>
            <input type="number" min="1" max="720" wire:model="form.duracion_estimada_horas"
                   class="mt-1 block w-full text-sm rounded border-gray-300"/>
        </div>
        <div></div>
HTML,
])
