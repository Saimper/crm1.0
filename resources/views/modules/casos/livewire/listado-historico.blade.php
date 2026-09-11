<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Histórico</h1>
            <p class="page-subtitle">Cuentas retiradas de la operación, con sus gestiones y compromisos.</p>
        </div>
        <x-ui.badge tone="neutral">Solo lectura</x-ui.badge>
    </div>

    <p class="text-sm text-ink-500 mb-4">Bajo supervisión del proyecto. Se conserva el asesor original para reportes; el administrador global puede habilitar la consulta a otros roles.</p>

    <div class="card">
        <x-ui.toolbar :count="numero_local($cuentas->total(), 0).' registros históricos'">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar por persona, identificación o cuenta…" />
            <select wire:model.live="cartera" class="input max-w-[250px]" aria-label="Cartera histórica">
                <option value="">Todas las carteras históricas</option>
                @foreach($carteras as $opcion)
                    <option value="{{ $opcion->cartera_public_id }}">{{ $opcion->cartera_nombre }}</option>
                @endforeach
            </select>
        </x-ui.toolbar>
        @can('historico.exportar', $proyectoId)
            <div class="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
                <span class="text-xs text-ink-500">Descargar con estos filtros:</span>
                @foreach(['cuentas' => 'Cuentas', 'gestiones' => 'Gestiones', 'compromisos' => 'Compromisos'] as $tipo => $etiqueta)
                    <a href="{{ route('proyectos.historico.exportar', ['proyecto_id' => $proyectoId, 'tipo' => $tipo] + $filtros) }}" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="download" :size="13" /> {{ $etiqueta }} CSV
                    </a>
                @endforeach
            </div>
        @endcan
        <x-ui.cargando />
        @if($cuentas->isEmpty())
            <x-ui.empty-state title="Sin registros históricos" message="No hay cuentas retiradas que coincidan con tus permisos y filtros." />
        @else
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr><th>Persona / cuenta</th><th>Cartera histórica</th><th>Saldo al retirar</th><th>Mora</th><th>Asesor original</th><th>Retirada</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($cuentas as $cuenta)
                            <tr wire:key="historico-{{ $cuenta->cursor_id }}">
                                <td>
                                    <div class="font-medium text-ink">{{ trim(($cuenta->nombres ?? '').' '.($cuenta->apellidos ?? '')) ?: $cuenta->razon_social }}</div>
                                    <div class="text-xs text-ink-500">{{ $cuenta->identificacion }}</div>
                                    <div class="font-mono text-sm mt-1">{{ $cuenta->referencia }}</div>
                                </td>
                                <td><div>{{ $cuenta->cartera_nombre }}</div><div class="text-xs text-ink-500 mt-1">{{ $cuenta->motivo }}</div></td>
                                <td class="font-mono whitespace-nowrap">{{ $cuenta->moneda }} {{ $cuenta->saldo_total === null ? '—' : numero_local($cuenta->saldo_total) }}</td>
                                <td class="whitespace-nowrap">{{ $cuenta->dias_mora === null ? '—' : numero_local($cuenta->dias_mora, 0).' días' }}</td>
                                <td>{{ $cuenta->asesor_nombre ?? 'Sin asignar' }}</td>
                                <td class="text-xs whitespace-nowrap">{{ hora_local($cuenta->fecha_archivo) }}</td>
                                <td>
                                    <a href="{{ route('proyectos.historico.ficha', ['proyecto_id' => $proyectoId, 'caso' => $cuenta->caso_public_id] + ($cuenta->archivo ? ['archivo' => $cuenta->archivo] : [])) }}" wire:navigate class="btn btn-ghost btn-sm">Consultar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $cuentas->links() }}</div>
        @endif
    </div>
</div>
