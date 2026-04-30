<div>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    @if(session('mensaje'))
        <div style="background:#d1fae5;border:1px solid #34d399;color:#065f46;padding:8px 12px;border-radius:6px;margin-bottom:12px;">
            {{ session('mensaje') }}
        </div>
    @endif

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div></div>
        @if($puedeGestionar)
            <a href="{{ route('proyectos.reportes.custom.nuevo', ['proyecto_id' => $proyecto->id]) }}"
               wire:navigate
               style="background:#2563eb;color:white;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:13px;">+ Nueva definición</a>
        @endif
    </div>

    @if(count($definiciones) === 0)
        <p style="color:#9ca3af;text-align:center;padding:24px;">Sin definiciones de reportes en este proyecto.</p>
    @else
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Código</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Nombre</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Entidad</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Estado</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($definiciones as $d)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:8px;font-family:monospace;font-size:11px;">{{ $d['codigo'] }}</td>
                        <td style="padding:8px;">{{ $d['nombre'] }}</td>
                        <td style="padding:8px;">{{ $d['entidad_raiz'] }}</td>
                        <td style="padding:8px;">{{ $d['activo'] ? 'Activo' : 'Inactivo' }}</td>
                        <td style="padding:8px;display:flex;gap:6px;flex-wrap:wrap;">
                            @if($puedeExportar && $d['activo'])
                                <a href="{{ route('proyectos.reportes.custom.exportar', ['proyecto_id' => $proyecto->id, 'definicion_id' => $d['id'], 'formato' => 'csv']) }}" style="font-size:11px;padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;text-decoration:none;color:#374151;">CSV</a>
                                <a href="{{ route('proyectos.reportes.custom.exportar', ['proyecto_id' => $proyecto->id, 'definicion_id' => $d['id'], 'formato' => 'xlsx']) }}" style="font-size:11px;padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;text-decoration:none;color:#374151;">XLSX</a>
                            @endif
                            @if($puedeGestionar)
                                <a href="{{ route('proyectos.reportes.custom.editar', ['proyecto_id' => $proyecto->id, 'definicion_id' => $d['id']]) }}" wire:navigate style="font-size:11px;padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;text-decoration:none;color:#374151;">Editar</a>
                                <button type="button" wire:click="eliminar({{ $d['id'] }})" wire:confirm="¿Eliminar definición '{{ $d['codigo'] }}'?" style="font-size:11px;padding:3px 8px;border:1px solid #dc2626;background:white;color:#dc2626;border-radius:4px;cursor:pointer;">Eliminar</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
