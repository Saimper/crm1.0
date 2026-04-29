@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'estado de caso',
    'items'    => $items,
    'cabecerasExtra' => fn (): string => '<th class="px-3 py-2 text-center">Terminal</th>',
    'filasExtra'     => fn (object $it): string => '<td class="px-3 py-2 text-center">'
        .($it->es_terminal ? '<span class="text-red-700">✓</span>' : '<span class="text-gray-300">—</span>')
        .'</td>',
    'camposExtra' => fn (): string => <<<'HTML'
        <div class="col-span-2">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="form.es_terminal" class="rounded"/>
                <span>Es estado terminal (caso cerrado)</span>
            </label>
        </div>
HTML,
])
