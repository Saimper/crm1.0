<div class="page">
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    @if(session('mensaje'))
        <div class="alert alert-success" style="margin-bottom:12px;">{{ session('mensaje') }}</div>
    @endif

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div></div>
        @if($puedeGestionar)
            <a href="{{ route('proyectos.reportes.custom.nuevo', ['proyecto_id' => $proyecto->id]) }}"
               wire:navigate
               class="btn btn-primary btn-sm">{{ __('reportes.new_definition') }}</a>
        @endif
    </div>

    @if(count($definiciones) === 0)
        <p style="color:var(--text-muted);text-align:center;padding:24px;">{{ __('reportes.empty_definitions') }}</p>
    @else
        <div class="card" style="overflow:hidden;">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>{{ __('reportes.col_code') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('reportes.col_entity') }}</th>
                        <th>{{ __('reportes.col_status') }}</th>
                        <th>{{ __('reportes.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($definiciones as $d)
                        <tr>
                            <td><span class="mono" style="font-size:11px;">{{ $d['codigo'] }}</span></td>
                            <td>{{ $d['nombre'] }}</td>
                            <td>{{ $d['entidad_raiz'] }}</td>
                            <td>
                                @if($d['activo'])
                                    <span class="badge badge-success">{{ __('reportes.status_active') }}</span>
                                @else
                                    <span class="badge badge-neutral">{{ __('reportes.status_inactive') }}</span>
                                @endif
                            </td>
                            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                @if($puedeExportar && $d['activo'])
                                    <a href="{{ route('proyectos.reportes.custom.exportar', ['proyecto_id' => $proyecto->id, 'definicion_id' => $d['id'], 'formato' => 'csv']) }}" class="btn btn-secondary btn-sm">CSV</a>
                                    <a href="{{ route('proyectos.reportes.custom.exportar', ['proyecto_id' => $proyecto->id, 'definicion_id' => $d['id'], 'formato' => 'xlsx']) }}" class="btn btn-secondary btn-sm">XLSX</a>
                                @endif
                                @if($puedeGestionar)
                                    <a href="{{ route('proyectos.reportes.custom.editar', ['proyecto_id' => $proyecto->id, 'definicion_id' => $d['id']]) }}" wire:navigate class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                    <button type="button" wire:click="eliminar({{ $d['id'] }})" wire:confirm="{{ __('reportes.confirm_delete', ['code' => $d['codigo']]) }}" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
