<x-app-layout>
    @php
        $proyecto = app('tenancy.proyecto_activo');
        $mandante = DB::table('mandantes')->find($proyecto->mandante_id);

        $tipoBadge = match ($proyecto->tipo_operacion) {
            'cobranza' => 'badge-warning',
            'cx'       => 'badge-primary',
            'venta'    => 'badge-success',
            'servicio' => 'badge-neutral',
            default    => 'badge-neutral',
        };

        $vigencia = ($proyecto->fecha_inicio ? \Illuminate\Support\Carbon::parse($proyecto->fecha_inicio)->format('d M Y') : '—')
            . ' → ' .
            ($proyecto->fecha_fin ? \Illuminate\Support\Carbon::parse($proyecto->fecha_fin)->format('d M Y') : '∞');

        $cards = [
            'Operación' => [
                ['can' => 'asignaciones.ver_propia', 'route' => 'proyectos.bandeja',        'title' => 'Bandeja',            'desc' => 'Asignaciones activas en el proyecto.',                                        'icon' => 'briefcase'],
                ['can' => 'personas.crear',           'route' => 'proyectos.personas.crear', 'title' => 'Crear persona',      'desc' => 'Registrar una persona física o jurídica.',                                    'icon' => 'plus'],
            ],
            'Supervisión' => [
                ['can' => 'reportes.operativos',    'route' => 'proyectos.reportes.operativos', 'title' => 'Reportes operativos',    'desc' => 'KPIs, efectividad, ranking, compromisos.',                                   'icon' => 'bar-chart'],
                ['can' => 'reportes.operativos',    'route' => 'proyectos.reportes.equipos',    'title' => 'Reporte por equipos',    'desc' => 'Métricas agregadas por equipo con breakdown por miembro.',                   'icon' => 'users'],
                ['can' => 'reportes.analiticos',    'route' => 'proyectos.reportes.analiticos', 'title' => 'Reportes analíticos',    'desc' => 'Distribución por tipo, tendencias, efectividad.',                             'icon' => 'pie-chart'],
                ['can' => 'asignaciones.ver_equipo','route' => 'proyectos.bandeja.equipo',      'title' => 'Bandeja del equipo',     'desc' => 'Asignaciones de los miembros con KPIs por gestor.',                           'icon' => 'briefcase'],
                ['can' => 'asignaciones.reasignar', 'route' => 'proyectos.asignaciones.masiva', 'title' => 'Asignación masiva',      'desc' => 'Distribuir casos pendientes round-robin al equipo.',                          'icon' => 'arrow-right'],
                ['can' => 'asignaciones.reasignar', 'route' => 'proyectos.asignaciones.reasignar','title' => 'Re-asignar entre equipos','desc' => 'Mover pendientes respetando casos en trabajo.',                           'icon' => 'refresh'],
            ],
            'Administración' => [
                ['can' => 'catalogos.gestionar', 'route' => 'proyectos.catalogos', 'title' => 'Catálogos del proyecto', 'desc' => 'Resultados, tipos, causas, motivos, estados.',          'icon' => 'tag'],
                ['can' => 'usuarios.gestionar',  'route' => 'proyectos.usuarios',  'title' => 'Usuarios del proyecto',  'desc' => 'Asignar y quitar roles SUPERVISOR/GESTOR/AUDITOR.',   'icon' => 'users'],
                ['can' => 'usuarios.gestionar',  'route' => 'proyectos.equipos',   'title' => 'Equipos del proyecto',   'desc' => 'Agrupar miembros en equipos para asignación.',        'icon' => 'briefcase'],
            ],
            'Trazabilidad' => [
                ['can' => 'auditoria.ver', 'route' => 'proyectos.auditoria', 'title' => 'Auditoría', 'desc' => 'Quién cambió qué, cuándo y desde qué IP.', 'icon' => 'shield'],
            ],
            'Datos' => [
                ['can' => 'importaciones.crear', 'route' => 'proyectos.importaciones', 'title' => 'Importar / Exportar', 'desc' => 'CSVs de personas, casos, gestiones, compromisos.', 'icon' => 'upload'],
            ],
        ];
    @endphp

    <div class="page space-y-6">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ $proyecto->nombre }}</h1>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge {{ $tipoBadge }}">{{ ucfirst($proyecto->tipo_operacion) }}</span>
                <span class="code-mono" style="font-size:11px;color:var(--text-tertiary);">{{ $proyecto->codigo }}</span>
                <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">← Cambiar proyecto</a>
            </div>
        </div>

        <div class="card card-pad">
            <div class="card-title" style="margin-bottom:4px;">{{ $mandante->nombre ?? 'Proyecto' }}</div>
            @if(! empty($proyecto->descripcion))
                <p style="font-size:12px;color:var(--text-tertiary);margin:0 0 14px;">{{ $proyecto->descripcion }}</p>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <div class="label-xs">Tipo de operación</div>
                    <div style="margin-top:6px;">
                        <span class="badge {{ $tipoBadge }}">{{ ucfirst($proyecto->tipo_operacion) }}</span>
                    </div>
                </div>
                <div>
                    <div class="label-xs">Vigencia</div>
                    <div style="margin-top:6px;font-size:13px;color:var(--text);">{{ $vigencia }}</div>
                </div>
                <div>
                    <div class="label-xs">Código</div>
                    <div class="code-mono" style="margin-top:6px;font-size:13px;color:var(--text);">{{ $proyecto->codigo }}</div>
                </div>
            </div>
        </div>

        @foreach($cards as $categoria => $items)
            @php
                $visibles = array_filter(
                    $items,
                    fn ($c) => \Illuminate\Support\Facades\Route::has($c['route'])
                        && auth()->user()?->tienePermiso($c['can'], $proyecto->id) === true
                );
            @endphp

            @if(! empty($visibles))
                <section>
                    <x-ui.section-title :title="$categoria" />
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($visibles as $c)
                            <a href="{{ route($c['route'], ['proyecto_id' => $proyecto->id]) }}"
                               wire:navigate
                               class="card card-pad proyecto-action-tile"
                               style="text-decoration:none;color:inherit;display:block;transition:border-color 120ms var(--ease), background 120ms var(--ease);">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0" style="height:40px;width:40px;border-radius:8px;background:var(--primary-soft);color:var(--primary-text);display:flex;align-items:center;justify-content:center;border:1px solid var(--primary-soft-border);">
                                        <x-ui.icon :name="$c['icon']" :size="18" />
                                    </div>
                                    <div class="min-w-0">
                                        <div style="font-weight:600;color:var(--text);font-size:14px;">{{ $c['title'] }}</div>
                                        <p style="margin-top:2px;font-size:12px;color:var(--text-tertiary);">{{ $c['desc'] }}</p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

    </div>
    <style>
        .proyecto-action-tile:hover { border-color: var(--primary-soft-border); background: var(--bg-subtle); }
    </style>
</x-app-layout>
