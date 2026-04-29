@php
    $meta = fn (object $it): array => is_string($it->metadata) ? (array) json_decode($it->metadata, true) : [];
@endphp

@include('catalogos::livewire._catalogo-simple', [
    'singular' => 'causa',
    'items'    => $items,
    'cabecerasExtra' => fn (): string => '<th class="px-3 py-2 text-left">Tipo</th>',
    'filasExtra'     => function (object $it) use ($meta): string {
        $tipo = $meta($it)['tipo'] ?? '';
        $tipoFmt = $tipo !== '' ? htmlspecialchars(ucfirst($tipo), ENT_QUOTES) : '—';
        return '<td class="px-3 py-2 text-xs text-gray-600">'.$tipoFmt.'</td>';
    },
    'camposExtra' => fn (): string => <<<'HTML'
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-700">Tipo semántico (opcional)</label>
            <select wire:model="form.tipo" class="mt-1 block w-full text-sm rounded border-gray-300">
                <option value="">—</option>
                <option value="mora">Mora (cobranza)</option>
                <option value="queja">Queja (CX)</option>
                <option value="rechazo">Rechazo (venta)</option>
                <option value="servicio">Servicio</option>
                <option value="otra">Otra</option>
            </select>
        </div>
HTML,
])
