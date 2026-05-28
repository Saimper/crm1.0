<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(session('crm_parent_origin'))
        <meta name="wrapper-origin" content="{{ session('crm_parent_origin') }}">
    @endif

    <title>{{ config('app.name', 'Núcleo CRM') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ sidebarOpen: false }">
    @php
        /** @var \App\Models\User|null $authUser */
        $authUser       = auth()->user();
        $proyectoActivo = app()->bound('tenancy.proyecto_activo')
            ? app('tenancy.proyecto_activo')
            : null;
        $esAdmin           = $authUser?->esAdminGlobal() ?? false;
        $esAdminMandante   = ! $esAdmin && $authUser !== null && $authUser->mandantesAdministrados() !== [];
        $esAdminAlguno     = $esAdmin || $esAdminMandante;
        $rid               = fn (string ...$names) => collect($names)->contains(fn ($n) => request()->routeIs($n));

        // Visibilidad por grupo del sidebar — un grupo se oculta entero si el usuario
        // no tiene ningún permiso de sus items. Evita títulos sueltos sin contenido.
        $puedeCambiarProyecto = $proyectoActivo && (
            $esAdmin || count($authUser?->proyectosAsignados() ?? []) > 1
        );
        $verGrupoReportes = $proyectoActivo && $authUser && (
            $authUser->tienePermiso('reportes.operativos', $proyectoActivo->id)
            || $authUser->tienePermiso('reportes.analiticos', $proyectoActivo->id)
            || $authUser->tienePermiso('reportes.constructor.ejecutar', $proyectoActivo->id)
        );
        // "Datos" = administración de datos del proyecto (importaciones). Los registros
        // operativos de entidades configurables se renderizan dentro de "Operación".
        $verGrupoDatos = $proyectoActivo && $authUser
            && $authUser->tienePermiso('importaciones.crear', $proyectoActivo->id);
        $verGrupoTrazabilidad = $proyectoActivo && $authUser && (
            $authUser->tienePermiso('auditoria.ver', $proyectoActivo->id)
            || $authUser->tienePermiso('notificaciones.ver', $proyectoActivo->id)
        );
    @endphp

    <div class="app" :class="{ 'sidebar-open': sidebarOpen }">

        {{-- Logo --}}
        <a href="{{ route('dashboard') }}" wire:navigate class="app-logo" style="text-decoration:none;color:inherit;">
            <span style="font-weight:600;font-size:14px;letter-spacing:-0.01em;">CRM</span>
        </a>

        {{-- Header --}}
        <header class="app-header">
            <button type="button" class="icon-btn md:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="{{ __('nav.menu') }}">
                <x-ui.icon name="layers" :size="16" />
            </button>

            <div class="breadcrumb" style="flex:1;min-width:0;">
                <a href="{{ route('dashboard') }}" wire:navigate style="color:var(--text-tertiary);">{{ __('nav.breadcrumb_projects') }}</a>
                @if($proyectoActivo)
                    <x-ui.icon name="chevron-right" :size="12" class="sep" />
                    <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyectoActivo->id]) }}"
                       wire:navigate style="color:var(--text-secondary);display:inline-flex;align-items:center;gap:6px;">
                        <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">
                            {{ $proyectoActivo->codigo ?? str_pad((string) $proyectoActivo->id, 4, '0', STR_PAD_LEFT) }}
                        </span>
                        <span>{{ $proyectoActivo->nombre }}</span>
                    </a>
                @endif
                @isset($breadcrumb)
                    {{ $breadcrumb }}
                @endisset
            </div>

            @if($proyectoActivo)
                <livewire:personas.buscador-global />
                <livewire:notificaciones.badge-notificaciones />
            @endif

            <livewire:layout.navigation />
        </header>

        {{-- Sidebar --}}
        <nav class="app-sidebar" :class="{ 'open': sidebarOpen }" @click.outside="sidebarOpen = false">

            @if($proyectoActivo)
                <div style="padding:0 12px 12px;">
                    <div class="card card-pad" style="padding:10px;background:var(--bg-subtle);">
                        <div class="label-xs" style="margin-bottom:4px;">{{ __('nav.active_project') }}</div>
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                            <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">
                                {{ $proyectoActivo->codigo ?? str_pad((string) $proyectoActivo->id, 4, '0', STR_PAD_LEFT) }}
                            </span>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;color:var(--text);font-weight:600;">
                                {{ $proyectoActivo->nombre }}
                            </span>
                        </div>
                        @if($puedeCambiarProyecto)
                            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost btn-sm"
                               style="width:100%;justify-content:center;text-decoration:none;"
                               title="{{ __('nav.back_to_selector') }}">
                                <x-ui.icon name="refresh" :size="13" />
                                <span>{{ __('nav.change_project') }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if($proyectoActivo)
                <div class="sb-group">
                    <div class="sb-group-title">{{ __('nav.group_operation') }}</div>
                    @can('asignaciones.ver_propia', $proyectoActivo->id)
                        <a href="{{ route('proyectos.bandeja', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.bandeja')) active @endif">
                            <x-ui.icon name="inbox" :size="15" />
                            <span>{{ __('nav.inbox') }}</span>
                        </a>
                    @endcan
                    @can('personas.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.personas.lista', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.personas.lista', 'proyectos.personas.crear')) active @endif">
                            <x-ui.icon name="user" :size="15" />
                            <span>{{ __('nav.people') }}</span>
                        </a>
                    @endcan
                    @can('casos.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.casos.lista', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.casos.lista')) active @endif">
                            <x-ui.icon name="folder" :size="15" />
                            <span>{{ __('nav.cases') }}</span>
                        </a>
                    @endcan
                    @can('compromisos.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.compromisos.lista', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.compromisos.lista')) active @endif">
                            <x-ui.icon name="tag" :size="15" />
                            <span>{{ __('nav.commitments') }}</span>
                        </a>
                    @endcan
                    @can('entidades.ver', $proyectoActivo->id)
                        @php
                            $entidadesProyecto = \Illuminate\Support\Facades\DB::table('entidades_configurables')
                                ->where('proyecto_id', $proyectoActivo->id)
                                ->whereNull('eliminada_en')
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->select(['id', 'nombre', 'icono'])
                                ->limit(15)
                                ->get();
                        @endphp
                        @foreach($entidadesProyecto as $ent)
                            <a href="{{ route('proyectos.entidades.registros', ['proyecto_id' => $proyectoActivo->id, 'entidad_id' => $ent->id]) }}"
                               wire:navigate
                               class="sb-item @if(request()->is('proyectos/'.$proyectoActivo->id.'/entidades/'.$ent->id)) active @endif">
                                <x-ui.icon :name="$ent->icono ?: 'layers'" :size="15" />
                                <span>{{ $ent->nombre }}</span>
                            </a>
                        @endforeach
                    @endcan
                    @can('asignaciones.ver_equipo', $proyectoActivo->id)
                        <a href="{{ route('proyectos.bandeja.equipo', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.bandeja.equipo')) active @endif">
                            <x-ui.icon name="users" :size="15" />
                            <span>{{ __('nav.team_inbox') }}</span>
                        </a>
                    @endcan
                    @can('asignaciones.reasignar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.asignaciones.masiva')) active @endif">
                            <x-ui.icon name="arrow-right" :size="15" />
                            <span>{{ __('nav.bulk_assignment') }}</span>
                        </a>
                    @endcan
                    @can('asignaciones.reasignar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.asignaciones.reasignar', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.asignaciones.reasignar')) active @endif">
                            <x-ui.icon name="refresh" :size="15" />
                            <span>{{ __('nav.reassignment') }}</span>
                        </a>
                    @endcan
                    @can('usuarios.gestionar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.equipos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.equipos')) active @endif">
                            <x-ui.icon name="briefcase" :size="15" />
                            <span>{{ __('nav.teams') }}</span>
                        </a>
                    @endcan
                </div>

                @if($verGrupoReportes)
                <div class="sb-group">
                    <div class="sb-group-title">{{ __('nav.group_reports') }}</div>
                    @can('reportes.operativos', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.operativos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.operativos')) active @endif">
                            <x-ui.icon name="bar-chart" :size="15" />
                            <span>{{ __('nav.reports_operational') }}</span>
                        </a>
                    @endcan
                    @can('reportes.analiticos', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.analiticos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.analiticos')) active @endif">
                            <x-ui.icon name="pie-chart" :size="15" />
                            <span>{{ __('nav.reports_analytical') }}</span>
                        </a>
                    @endcan
                    @can('reportes.operativos', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.equipos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.equipos')) active @endif">
                            <x-ui.icon name="users" :size="15" />
                            <span>{{ __('nav.reports_by_team') }}</span>
                        </a>
                    @endcan
                    @can('reportes.constructor.ejecutar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.custom', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.custom', 'proyectos.reportes.custom.nuevo', 'proyectos.reportes.custom.editar')) active @endif">
                            <x-ui.icon name="layers" :size="15" />
                            <span>{{ __('nav.reports_custom') }}</span>
                        </a>
                    @endcan
                </div>
                @endif

                @if($verGrupoDatos)
                <div class="sb-group">
                    <div class="sb-group-title">{{ __('nav.group_data') }}</div>
                    {{-- Catálogos y Carteras absorbidos por el wizard "Configurar proyecto" (F36 P8). --}}
                    @can('importaciones.crear', $proyectoActivo->id)
                        <a href="{{ route('proyectos.importaciones', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.importaciones*')) active @endif">
                            <x-ui.icon name="upload" :size="15" />
                            <span>{{ __('nav.imports') }}</span>
                        </a>
                    @endcan
                </div>
                @endif

                @if($verGrupoTrazabilidad)
                <div class="sb-group">
                    <div class="sb-group-title">{{ __('nav.group_traceability') }}</div>
                    @can('auditoria.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.auditoria', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.auditoria*')) active @endif">
                            <x-ui.icon name="shield" :size="15" />
                            <span>{{ __('nav.audit') }}</span>
                        </a>
                    @endcan
                    @can('notificaciones.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.notificaciones', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.notificaciones')) active @endif">
                            <x-ui.icon name="bell" :size="15" />
                            <span>{{ __('nav.notifications') }}</span>
                        </a>
                    @endcan
                </div>
                @endif

                @can('usuarios.gestionar', $proyectoActivo->id)
                    <div class="sb-group">
                        <div class="sb-group-title">{{ __('nav.group_supervision') }}</div>
                        <a href="{{ route('proyectos.usuarios', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.usuarios')) active @endif">
                            <x-ui.icon name="user" :size="15" />
                            <span>{{ __('nav.project_users') }}</span>
                        </a>
                    </div>
                @endcan

                @can('roles.gestionar', $proyectoActivo->id)
                    <div class="sb-group">
                        <div class="sb-group-title">{{ __('nav.group_permissions') }}</div>
                        <a href="{{ route('proyectos.admin.roles-custom', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.admin.roles-custom')) active @endif">
                            <x-ui.icon name="shield" :size="15" />
                            <span>{{ __('nav.custom_roles') }}</span>
                        </a>
                        <a href="{{ route('proyectos.admin.matriz-permisos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.admin.matriz-permisos')) active @endif">
                            <x-ui.icon name="hash" :size="15" />
                            <span>{{ __('nav.permissions_matrix') }}</span>
                        </a>
                    </div>
                @endcan

                @can('proyectos.configurar', $proyectoActivo->id)
                    @php
                        // Avance se calcula UNA sola vez por render del layout.
                        // El layout se monta una vez por request HTTP, así que
                        // este bloque dispara los 9 verificadores a lo sumo 1
                        // vez por página servida.
                        $avanceConfigurador = app(\App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion::class)
                            ->calcular((int) $proyectoActivo->id);

                        if ($avanceConfigurador->estaCompleto()) {
                            $configHref    = route('admin.proyectos.configurar.editar', ['proyecto' => $proyectoActivo->public_id]);
                            $configTooltip = __('nav.configure_project_edit_tooltip');
                        } else {
                            $configHref    = route('admin.proyectos.configurar', ['proyecto' => $proyectoActivo->public_id]);
                            $configTooltip = __('nav.configure_project_init_tooltip');
                        }
                    @endphp
                    <div class="sb-group">
                        <div class="sb-group-title">{{ __('nav.group_configuration') }}</div>
                        <a href="{{ $configHref }}" wire:navigate
                           title="{{ $configTooltip }}"
                           class="sb-item @if($rid('admin.proyectos.configurar', 'admin.proyectos.configurar.editar')) active @endif">
                            <x-ui.icon name="settings" :size="15" />
                            <span>{{ __('nav.configure_project') }}</span>
                        </a>
                    </div>
                @endcan
            @endif

            @if($esAdminAlguno)
                <div class="sb-group">
                    <div class="sb-group-title">{{ $esAdmin ? __('nav.group_administration') : __('nav.group_administration_mandante') }}</div>
                    <a href="{{ route('admin.dashboard') }}" wire:navigate
                       class="sb-item @if($rid('admin.dashboard')) active @endif">
                        <x-ui.icon name="bar-chart" :size="15" />
                        <span>{{ __('nav.admin_panel') }}</span>
                    </a>
                    <a href="{{ route('admin.proyectos') }}" wire:navigate
                       class="sb-item @if($rid('admin.proyectos')) active @endif">
                        <x-ui.icon name="briefcase" :size="15" />
                        <span>{{ __('nav.projects') }}</span>
                    </a>
                    @if($esAdmin)
                        <a href="{{ route('admin.mandantes') }}" wire:navigate
                           class="sb-item @if($rid('admin.mandantes')) active @endif">
                            <x-ui.icon name="building" :size="15" />
                            <span>{{ __('nav.mandantes') }}</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.usuarios') }}" wire:navigate
                       class="sb-item @if($rid('admin.usuarios')) active @endif">
                        <x-ui.icon name="users" :size="15" />
                        <span>{{ __('nav.users') }}</span>
                    </a>
                    @if($esAdmin)
                        <a href="{{ route('admin.campos-personalizados') }}" wire:navigate
                           class="sb-item @if($rid('admin.campos-personalizados')) active @endif">
                            <x-ui.icon name="hash" :size="15" />
                            <span>{{ __('nav.custom_fields') }}</span>
                        </a>
                        <a href="{{ route('admin.entidades-configurables') }}" wire:navigate
                           class="sb-item @if($rid('admin.entidades-configurables')) active @endif">
                            <x-ui.icon name="layers" :size="15" />
                            <span>{{ __('nav.configurable_entities') }}</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.auditoria') }}" wire:navigate
                       class="sb-item @if($rid('admin.auditoria')) active @endif">
                        <x-ui.icon name="shield" :size="15" />
                        <span>{{ $esAdmin ? __('nav.audit_global') : __('nav.audit') }}</span>
                    </a>
                    @if($esAdmin)
                        <a href="{{ route('admin.integracion.secrets') }}" wire:navigate
                           class="sb-item @if($rid('admin.integracion.secrets')) active @endif">
                            <x-ui.icon name="key" :size="15" />
                            <span>{{ __('nav.sso_secrets') }}</span>
                        </a>
                    @endif
                </div>
            @endif

            @if(!$proyectoActivo && !$esAdminAlguno)
                <div class="sb-group">
                    <div class="sb-group-title">{{ __('nav.group_home') }}</div>
                    <a href="{{ route('dashboard') }}" wire:navigate
                       class="sb-item @if($rid('dashboard')) active @endif">
                        <x-ui.icon name="briefcase" :size="15" />
                        <span>{{ __('nav.my_projects') }}</span>
                    </a>
                </div>
            @endif
        </nav>

        {{-- Main --}}
        <main class="app-main">
            @isset($header)
                {{ $header }}
            @endisset
            {{ $slot }}
        </main>
    </div>

    @stack('modals')
</body>
</html>
