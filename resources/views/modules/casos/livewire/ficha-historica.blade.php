<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Cuenta histórica {{ $cuenta->referencia }}</h1>
            <p class="page-subtitle">{{ $cuenta->cartera_nombre }} · {{ $cuenta->motivo }}</p>
        </div>
        <a href="{{ route('proyectos.historico.lista', ['proyecto_id' => $proyectoId]) }}" wire:navigate class="btn btn-ghost btn-sm">Volver al histórico</a>
    </div>
    <div class="grid gap-4 md:grid-cols-2 mb-4">
        <x-ui.card title="Persona y cuenta">
            <div class="font-semibold text-ink">{{ trim(($cuenta->nombres ?? '').' '.($cuenta->apellidos ?? '')) ?: $cuenta->razon_social }}</div>
            <div class="font-mono text-sm text-ink-500 mt-1">{{ $cuenta->identificacion }}</div>
            <dl class="grid grid-cols-2 gap-3 mt-4 text-sm">
                <div><dt class="text-ink-500">Referencia</dt><dd class="font-mono mt-1">{{ $cuenta->referencia }}</dd></div>
                <div><dt class="text-ink-500">Estado al retirar</dt><dd class="mt-1">{{ $cuenta->estado_nombre ?? '—' }}</dd></div>
                <div><dt class="text-ink-500">Saldo al retirar</dt><dd class="font-mono mt-1">{{ $cuenta->moneda }} {{ $cuenta->saldo_total === null ? '—' : numero_local($cuenta->saldo_total) }}</dd></div>
                <div><dt class="text-ink-500">Mora al retirar</dt><dd class="mt-1">{{ $cuenta->dias_mora === null ? '—' : numero_local($cuenta->dias_mora, 0).' días' }}</dd></div>
            </dl>
        </x-ui.card>
        <x-ui.card title="Conservación del historial">
            <x-ui.badge tone="neutral">Solo lectura</x-ui.badge>
            <dl class="grid gap-3 mt-4 text-sm">
                <div><dt class="text-ink-500">Cartera de origen</dt><dd class="mt-1">{{ $cuenta->cartera_nombre }}</dd></div>
                <div><dt class="text-ink-500">Asesor original</dt><dd class="mt-1">{{ $cuenta->asesor_nombre ?? 'Sin asignar' }}</dd></div>
                <div><dt class="text-ink-500">Fecha de retirada</dt><dd class="mt-1">{{ hora_local($cuenta->fecha_archivo) }}</dd></div>
            </dl>
            <p class="text-xs text-ink-500 mt-4">Las gestiones conservan su autor. Los compromisos muestran su estado actual, aunque se hayan resuelto después de retirar la cuenta.</p>
            <p class="text-xs text-ink-500 mt-3">Bajo supervisión del proyecto. Se conserva el asesor original para reportes; el administrador global puede habilitar la consulta a otros roles.</p>
        </x-ui.card>
    </div>
    @can('historico.exportar', $proyectoId)
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach(['cuentas' => 'Cuenta', 'gestiones' => 'Gestiones', 'compromisos' => 'Compromisos'] as $tipo => $etiqueta)
                <a href="{{ route('proyectos.historico.exportar', ['proyecto_id' => $proyectoId, 'tipo' => $tipo] + $filtros) }}" class="btn btn-secondary btn-sm"><x-ui.icon name="download" :size="13" /> {{ $etiqueta }} CSV</a>
            @endforeach
        </div>
    @endcan
    <x-ui.cargando />
    <div class="card mb-4">
        <div class="card-header"><h2 class="font-semibold">Gestiones · {{ numero_local($gestiones->total(), 0) }}</h2></div>
        @if($gestiones->isEmpty())
            <x-ui.empty-state title="Sin gestiones en esta cartera" message="No se registraron gestiones durante esta etapa de la cuenta." />
        @else
            <div class="divide-y divide-border">
                @foreach($gestiones as $gestion)
                    <article class="p-4" wire:key="gestion-historica-{{ $gestion->registro_id }}">
                        <div class="flex flex-wrap justify-between gap-2"><strong class="text-sm">{{ $gestion->resultado_nombre }}</strong><time class="text-xs text-ink-500">{{ hora_local($gestion->creada_en) }}</time></div>
                        <div class="text-xs text-ink-500 mt-1">{{ $gestion->autor_nombre }} · {{ $gestion->canal_nombre }} · {{ $gestion->tipo_nombre }}</div>
                        <p class="text-sm text-ink mt-3 whitespace-pre-wrap break-words">{{ $gestion->notas }}</p>
                    </article>
                @endforeach
            </div>
            <div class="card-footer">{{ $gestiones->links() }}</div>
        @endif
    </div>
    <div class="card">
        <div class="card-header"><h2 class="font-semibold">Compromisos · {{ numero_local($compromisos->total(), 0) }}</h2></div>
        @if($compromisos->isEmpty())
            <x-ui.empty-state title="Sin compromisos en esta cartera" message="No se registraron compromisos durante esta etapa de la cuenta." />
        @else
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead><tr><th>Compromiso</th><th>Autor original</th><th>Vencimiento</th><th>Importe / detalle</th><th>Estado actual</th><th>Resolución</th></tr></thead>
                    <tbody>
                        @foreach($compromisos as $compromiso)
                            <tr wire:key="compromiso-historico-{{ $compromiso->registro_id }}">
                                <td>{{ match($compromiso->tipo_compromiso) { 'promesa_pago' => 'Promesa de pago', 'cierre_venta' => 'Cierre de venta', 'resolucion_ticket' => 'Resolución', default => 'Acción de servicio' } }}</td>
                                <td>{{ $compromiso->autor_nombre }}</td>
                                <td>{{ fecha_local($compromiso->fecha_vencimiento) }}</td>
                                <td>@if($compromiso->monto_compromiso !== null)<span class="font-mono">{{ $compromiso->moneda_compromiso }} {{ numero_local($compromiso->monto_compromiso) }}</span>@else{{ $compromiso->detalle ?? '—' }}@endif</td>
                                <td>{{ ucfirst($compromiso->estado) }}</td>
                                <td>{{ $compromiso->fecha_resolucion ? fecha_local($compromiso->fecha_resolucion) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $compromisos->links() }}</div>
        @endif
    </div>
</div>
