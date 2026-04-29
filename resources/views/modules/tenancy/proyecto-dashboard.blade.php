<x-app-layout>
    @php
        $proyecto = app('tenancy.proyecto_activo');
        $mandante = DB::table('mandantes')->find($proyecto->mandante_id);

        $tipoTone = match ($proyecto->tipo_operacion) {
            'cobranza' => 'warning',
            'cx'       => 'info',
            'venta'    => 'success',
            'servicio' => 'accent',
            default    => 'neutral',
        };

        $vigencia = ($proyecto->fecha_inicio ? \Illuminate\Support\Carbon::parse($proyecto->fecha_inicio)->format('d M Y') : '—')
            . ' → ' .
            ($proyecto->fecha_fin ? \Illuminate\Support\Carbon::parse($proyecto->fecha_fin)->format('d M Y') : '∞');

        // Tarjetas de acción por categoría
        $cards = [
            'Operación' => [
                ['can' => 'asignaciones.ver_propia', 'route' => 'proyectos.bandeja',        'title' => 'Bandeja',            'desc' => 'Asignaciones activas en el proyecto.',                                        'icon' => 'briefcase'],
                ['can' => 'personas.crear',           'route' => 'proyectos.personas.crear', 'title' => 'Crear persona',      'desc' => 'Registrar una persona física o jurídica.',                                    'icon' => 'plus'],
            ],
            'Supervisión' => [
                ['can' => 'reportes.operativos',    'route' => 'proyectos.reportes.operativos', 'title' => 'Reportes operativos',    'desc' => 'KPIs, efectividad, ranking, compromisos.',                                   'icon' => 'chart-bar'],
                ['can' => 'reportes.operativos',    'route' => 'proyectos.reportes.equipos',    'title' => 'Reporte por equipos',    'desc' => 'Métricas agregadas por equipo con breakdown por miembro.',                   'icon' => 'users'],
                ['can' => 'reportes.analiticos',    'route' => 'proyectos.reportes.analiticos', 'title' => 'Reportes analíticos',    'desc' => 'Distribución por tipo, tendencias, efectividad.',                             'icon' => 'chart-bar'],
                ['can' => 'asignaciones.ver_equipo','route' => 'proyectos.bandeja.equipo',      'title' => 'Bandeja del equipo',     'desc' => 'Asignaciones de los miembros con KPIs por gestor.',                           'icon' => 'briefcase'],
                ['can' => 'asignaciones.reasignar', 'route' => 'proyectos.asignaciones.masiva', 'title' => 'Asignación masiva',      'desc' => 'Distribuir casos pendientes round-robin al equipo.',                          'icon' => 'arrow-right'],
                ['can' => 'asignaciones.reasignar', 'route' => 'proyectos.asignaciones.reasignar','title' => 'Re-asignar entre equipos','desc' => 'Mover pendientes respetando casos en trabajo.',                           'icon' => 'arrow-right'],
            ],
            'Administración' => [
                ['can' => 'catalogos.gestionar', 'route' => 'proyectos.catalogos', 'title' => 'Catálogos del proyecto', 'desc' => 'Resultados, tipos, causas, motivos, estados.',          'icon' => 'clipboard'],
                ['can' => 'usuarios.gestionar',  'route' => 'proyectos.usuarios',  'title' => 'Usuarios del proyecto',  'desc' => 'Asignar y quitar roles SUPERVISOR/GESTOR/AUDITOR.',   'icon' => 'users'],
                ['can' => 'usuarios.gestionar',  'route' => 'proyectos.equipos',   'title' => 'Equipos del proyecto',   'desc' => 'Agrupar miembros en equipos para asignación.',        'icon' => 'users'],
            ],
            'Trazabilidad' => [
                ['can' => 'auditoria.ver', 'route' => 'proyectos.auditoria', 'title' => 'Auditoría', 'desc' => 'Quién cambió qué, cuándo y desde qué IP.', 'icon' => 'shield'],
            ],
            'Datos' => [
                ['can' => 'importaciones.crear', 'route' => 'proyectos.importaciones', 'title' => 'Importar / Exportar', 'desc' => 'CSVs de personas, casos, gestiones, compromisos.', 'icon' => 'clipboard'],
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-ui.page-header :title="$proyecto->nombre">
            <x-slot name="actions">
                <x-ui.badge :tone="$tipoTone">{{ ucfirst($proyecto->tipo_operacion) }}</x-ui.badge>
                <span class="text-xs text-ink-500 font-mono">{{ $proyecto->codigo }}</span>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-ui.card :title="$mandante->nombre ?? 'Proyecto'" :subtitle="$proyecto->descripcion ?? null">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-[11px] uppercase tracking-wider font-medium text-ink-500">Tipo de operación</div>
                        <div class="mt-1.5">
                            <x-ui.badge :tone="$tipoTone">{{ ucfirst($proyecto->tipo_operacion) }}</x-ui.badge>
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider font-medium text-ink-500">Vigencia</div>
                        <div class="mt-1.5 text-ink-800">{{ $vigencia }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider font-medium text-ink-500">Código</div>
                        <div class="mt-1.5 text-ink-800 font-mono">{{ $proyecto->codigo }}</div>
                    </div>
                </div>
            </x-ui.card>

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
                                   class="group rounded-xl border border-surface-border bg-white p-5 shadow-card hover:shadow-card-hover hover:border-brand-300 transition-all">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center group-hover:bg-brand-100 transition-colors">
                                            <x-ui.icon :name="$c['icon']" class="w-5 h-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-ink-900 group-hover:text-brand-700 transition-colors">{{ $c['title'] }}</div>
                                            <p class="mt-0.5 text-xs text-ink-500">{{ $c['desc'] }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

        </div>
    </div>
</x-app-layout>
