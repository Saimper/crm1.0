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
            __('tenancy.section_operation') => [
                ['can' => 'asignaciones.ver_propia', 'route' => 'proyectos.bandeja',        'title' => __('tenancy.tile_bandeja_title'),         'desc' => __('tenancy.tile_bandeja_desc'),         'icon' => 'briefcase'],
                ['can' => 'personas.crear',           'route' => 'proyectos.personas.crear', 'title' => __('tenancy.tile_crear_persona_title'),   'desc' => __('tenancy.tile_crear_persona_desc'),   'icon' => 'plus'],
            ],
            __('tenancy.section_supervision') => [
                ['can' => 'reportes.operativos',    'route' => 'proyectos.reportes.operativos',    'title' => __('tenancy.tile_reportes_op_title'),     'desc' => __('tenancy.tile_reportes_op_desc'),      'icon' => 'bar-chart'],
                ['can' => 'reportes.operativos',    'route' => 'proyectos.reportes.equipos',        'title' => __('tenancy.tile_reportes_eq_title'),     'desc' => __('tenancy.tile_reportes_eq_desc'),      'icon' => 'users'],
                ['can' => 'reportes.analiticos',    'route' => 'proyectos.reportes.analiticos',     'title' => __('tenancy.tile_reportes_an_title'),     'desc' => __('tenancy.tile_reportes_an_desc'),      'icon' => 'pie-chart'],
                ['can' => 'asignaciones.ver_equipo','route' => 'proyectos.bandeja.equipo',          'title' => __('tenancy.tile_bandeja_equipo_title'),  'desc' => __('tenancy.tile_bandeja_equipo_desc'),   'icon' => 'briefcase'],
                ['can' => 'asignaciones.reasignar', 'route' => 'proyectos.asignaciones.masiva',     'title' => __('tenancy.tile_asig_masiva_title'),     'desc' => __('tenancy.tile_asig_masiva_desc', ['entidades' => $rotuloCasos]),      'icon' => 'arrow-right'],
                ['can' => 'asignaciones.reasignar', 'route' => 'proyectos.asignaciones.reasignar',  'title' => __('tenancy.tile_reasignar_title'),       'desc' => __('tenancy.tile_reasignar_desc', ['entidades' => $rotuloCasos]),        'icon' => 'refresh'],
            ],
            __('tenancy.section_administration') => [
                // Tile "Catálogos del proyecto" absorbido por el wizard "Configurar proyecto" (F36 P9).
                ['can' => 'usuarios.gestionar',  'route' => 'proyectos.usuarios',  'title' => __('tenancy.tile_usuarios_proy_title'),  'desc' => __('tenancy.tile_usuarios_proy_desc'),  'icon' => 'users'],
                ['can' => 'usuarios.gestionar',  'route' => 'proyectos.equipos',   'title' => __('tenancy.tile_equipos_proy_title'),   'desc' => __('tenancy.tile_equipos_proy_desc'),   'icon' => 'briefcase'],
            ],
            __('tenancy.section_traceability') => [
                ['can' => 'auditoria.ver', 'route' => 'proyectos.auditoria', 'title' => __('tenancy.tile_auditoria_title'), 'desc' => __('tenancy.tile_auditoria_desc'), 'icon' => 'shield'],
            ],
            __('tenancy.section_data') => [
                ['can' => 'importaciones.crear', 'route' => 'proyectos.importaciones', 'title' => __('tenancy.tile_importar_title'), 'desc' => __('tenancy.tile_importar_desc', ['entidades' => $rotuloCasos]), 'icon' => 'upload'],
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
                <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('tenancy.change_project') }}</a>
            </div>
        </div>

        <div class="card card-pad">
            <div class="card-title" style="margin-bottom:4px;">{{ $mandante->nombre ?? 'Proyecto' }}</div>
            @if(! empty($proyecto->descripcion))
                <p style="font-size:12px;color:var(--text-tertiary);margin:0 0 14px;">{{ $proyecto->descripcion }}</p>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <div class="label-xs">{{ __('tenancy.label_operation_type') }}</div>
                    <div style="margin-top:6px;">
                        <span class="badge {{ $tipoBadge }}">{{ ucfirst($proyecto->tipo_operacion) }}</span>
                    </div>
                </div>
                <div>
                    <div class="label-xs">{{ __('tenancy.label_validity') }}</div>
                    <div style="margin-top:6px;font-size:13px;color:var(--text);">{{ $vigencia }}</div>
                </div>
                <div>
                    <div class="label-xs">{{ __('tenancy.label_code_project') }}</div>
                    <div class="code-mono" style="margin-top:6px;font-size:13px;color:var(--text);">{{ $proyecto->codigo }}</div>
                </div>
            </div>
        </div>

        {{-- F34C P3-3: KPIs de entrada para gestores --}}
        @php
            $usuarioId = (int) auth()->id();
            $hoy = \Illuminate\Support\Carbon::today();
        @endphp

        {{-- El resumen del día vive en su propio componente: la vista no calcula
             (§13.4), y así el mismo panel puede reutilizarse en otra pantalla. --}}
        <livewire:reportes.panel-del-dia :proyecto-id="$proyecto->id" />

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
