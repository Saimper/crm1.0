<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Compromisos del proyecto</h1>
            <div class="page-subtitle">
                <strong>{{ $resumen['pendientes'] }}</strong> pendientes ·
                <span style="color:var(--danger);"><strong>{{ $resumen['vencidos'] }}</strong> vencidos</span> ·
                {{ $resumen['cumplidos'] }} cumplidos · {{ $resumen['rotos'] }} rotos
            </div>
        </div>
    </div>

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <select wire:model.live="estado" class="input" style="width:160px;">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="cumplido">Cumplido</option>
                <option value="roto">Roto</option>
                <option value="cancelado">Cancelado</option>
            </select>
            <select wire:model.live="vencimiento" class="input" style="width:160px;">
                <option value="">Cualquier vencimiento</option>
                <option value="vigentes">Vigentes</option>
                <option value="vencidos">Vencidos</option>
                <option value="proximos7d">Próximos 7 días</option>
            </select>
            <select wire:model.live="tipoCompromiso" class="input" style="width:200px;">
                <option value="">Todos los tipos</option>
                <option value="promesa_pago">Promesa de pago</option>
                <option value="resolucion_ticket">Resolución ticket</option>
                <option value="cierre_venta">Cierre de venta</option>
                <option value="accion_servicio">Acción de servicio</option>
            </select>
            @if($estado !== '' || $vencimiento !== '' || $tipoCompromiso !== '')
                <button type="button" wire:click="limpiarFiltros" class="btn btn-ghost btn-sm">Limpiar</button>
            @endif
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ $compromisos->total() }} resultados</span>
        </div>

        @if($compromisos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="tag" :size="32" /></div>
                <div class="empty-title">Sin compromisos</div>
                <div class="empty-desc">No hay compromisos que coincidan con los filtros.</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:130px;">Tipo</th>
                        <th style="width:100px;">Estado</th>
                        <th>Persona</th>
                        <th>Identificación</th>
                        <th>Usuario</th>
                        <th style="width:120px;">Vencimiento</th>
                        <th style="width:120px;">Resuelto</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($compromisos as $c)
                        @php
                            $nombre = $c->tipo_persona === 'juridica'
                                ? ($c->razon_social ?? '—')
                                : trim(($c->nombres ?? '').' '.($c->apellidos ?? ''));
                            $estadoTone = match ($c->estado) {
                                'cumplido' => 'success',
                                'roto' => 'danger',
                                'cancelado' => 'neutral',
                                'pendiente' => $c->fecha_vencimiento < now()->format('Y-m-d') ? 'danger' : 'warning',
                                default => 'neutral',
                            };
                            $url = $c->persona_public_id
                                ? route('proyectos.trabajo', [
                                    'proyecto_id' => app('tenancy.proyecto_activo')->id,
                                    'persona' => $c->persona_public_id,
                                    'caso' => $c->caso_public_id,
                                ])
                                : null;
                        @endphp
                        <tr wire:key="comp-{{ $c->id }}"
                            @if($url) onclick="window.Livewire.navigate('{{ $url }}')" style="cursor:pointer;" @endif>
                            <td>
                                <span style="font-size:11px;">
                                    {{ str_replace('_', ' ', $c->tipo_compromiso) }}
                                </span>
                            </td>
                            <td>
                                <x-ui.badge :tone="$estadoTone" size="sm">
                                    {{ ucfirst($c->estado) }}
                                </x-ui.badge>
                            </td>
                            <td><span style="font-weight:500;">{{ $nombre !== '' ? $nombre : '—' }}</span></td>
                            <td><span class="font-mono" style="font-size:12px;">{{ $c->identificacion }}</span></td>
                            <td style="font-size:12px;color:var(--text-secondary);">{{ $c->usuario_nombre ?? '—' }}</td>
                            <td style="font-size:12px;">
                                {{ $c->fecha_vencimiento ? \Illuminate\Support\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') : '—' }}
                            </td>
                            <td style="font-size:12px;color:var(--text-secondary);">
                                {{ $c->fecha_resolucion ? \Illuminate\Support\Carbon::parse($c->fecha_resolucion)->format('d/m/Y') : '—' }}
                            </td>
                            <td>
                                @if($url)
                                    <x-ui.icon name="chevron-right" :size="14" style="color:var(--text-muted);" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="padding:10px 16px;border-top:1px solid var(--border);">
                {{ $compromisos->links() }}
            </div>
        @endif
    </div>
</div>
