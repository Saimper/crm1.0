@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'producto de venta',
    'items'    => $items,
    'camposExtra' => fn (): string => <<<'HTML'
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-700">Descripción (opcional)</label>
            <textarea wire:model="form.descripcion" rows="2"
                      class="mt-1 block w-full text-sm rounded border-gray-300"></textarea>
        </div>
HTML,
])
