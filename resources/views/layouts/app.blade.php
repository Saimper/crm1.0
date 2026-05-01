<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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
        $esAdmin        = $authUser?->esAdminGlobal() ?? false;
        $rid            = fn (string ...$names) => collect($names)->contains(fn ($n) => request()->routeIs($n));
    @endphp

    <div class="app" :class="{ 'sidebar-open': sidebarOpen }">

        {{-- Logo --}}
        <a href="{{ route('dashboard') }}" wire:navigate class="app-logo" style="text-decoration:none;color:inherit;">
            <x-ui.icon name="logo" :size="22" />
            <span style="font-weight:600;font-size:14px;letter-spacing:-0.01em;">Núcleo</span>
            <span style="font-size:10px;font-weight:500;color:var(--text-muted);border:1px solid var(--border);padding:1px 5px;border-radius:3px;margin-left:2px;">CRM</span>
        </a>

        {{-- Header --}}
        <header class="app-header">
            <button type="button" class="icon-btn md:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="Menú">
                <x-ui.icon name="layers" :size="16" />
            </button>

            <div class="breadcrumb" style="flex:1;min-width:0;">
                <a href="{{ route('dashboard') }}" wire:navigate style="color:var(--text-tertiary);">Proyectos</a>
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
                        <div class="label-xs" style="margin-bottom:4px;">Proyecto activo</div>
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                            <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">
                                {{ $proyectoActivo->codigo ?? str_pad((string) $proyectoActivo->id, 4, '0', STR_PAD_LEFT) }}
                            </span>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;color:var(--text);font-weight:600;">
                                {{ $proyectoActivo->nombre }}
                            </span>
                        </div>
                        <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost btn-sm"
                           style="width:100%;justify-content:center;text-decoration:none;"
                           title="Volver al selector de proyectos">
                            <x-ui.icon name="refresh" :size="13" />
                            <span>Cambiar proyecto</span>
                        </a>
                    </div>
                </div>
            @endif

            @if($proyectoActivo)
                <div class="sb-group">
                    <div class="sb-group-title">Operación</div>
                    @can('asignaciones.ver_propia', $proyectoActivo->id)
                        <a href="{{ route('proyectos.bandeja', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.bandeja')) active @endif">
                            <x-ui.icon name="inbox" :size="15" />
                            <span>Mi Bandeja</span>
                        </a>
                    @endcan
                    @can('personas.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.personas.lista', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.personas.lista', 'proyectos.personas.crear')) active @endif">
                            <x-ui.icon name="user" :size="15" />
                            <span>Personas</span>
                        </a>
                    @endcan
                    @can('casos.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.casos.lista', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.casos.lista')) active @endif">
                            <x-ui.icon name="folder" :size="15" />
                            <span>Casos</span>
                        </a>
                    @endcan
                    @can('compromisos.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.compromisos.lista', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.compromisos.lista')) active @endif">
                            <x-ui.icon name="tag" :size="15" />
                            <span>Compromisos</span>
                        </a>
                    @endcan
                    @can('asignaciones.ver_equipo', $proyectoActivo->id)
                        <a href="{{ route('proyectos.bandeja.equipo', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.bandeja.equipo')) active @endif">
                            <x-ui.icon name="users" :size="15" />
                            <span>Bandeja del Equipo</span>
                        </a>
                    @endcan
                    @can('asignaciones.reasignar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.asignaciones.masiva', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.asignaciones.masiva')) active @endif">
                            <x-ui.icon name="arrow-right" :size="15" />
                            <span>Asignación Masiva</span>
                        </a>
                    @endcan
                    @can('asignaciones.reasignar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.asignaciones.reasignar', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.asignaciones.reasignar')) active @endif">
                            <x-ui.icon name="refresh" :size="15" />
                            <span>Reasignación</span>
                        </a>
                    @endcan
                    @can('usuarios.gestionar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.equipos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.equipos')) active @endif">
                            <x-ui.icon name="briefcase" :size="15" />
                            <span>Equipos</span>
                        </a>
                    @endcan
                </div>

                <div class="sb-group">
                    <div class="sb-group-title">Reportes</div>
                    @can('reportes.operativos', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.operativos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.operativos')) active @endif">
                            <x-ui.icon name="bar-chart" :size="15" />
                            <span>Operativos</span>
                        </a>
                    @endcan
                    @can('reportes.analiticos', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.analiticos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.analiticos')) active @endif">
                            <x-ui.icon name="pie-chart" :size="15" />
                            <span>Analíticos</span>
                        </a>
                    @endcan
                    @can('reportes.operativos', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.equipos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.equipos')) active @endif">
                            <x-ui.icon name="users" :size="15" />
                            <span>Por Equipo</span>
                        </a>
                    @endcan
                    @can('reportes.constructor.ejecutar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.reportes.custom', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.reportes.custom', 'proyectos.reportes.custom.nuevo', 'proyectos.reportes.custom.editar')) active @endif">
                            <x-ui.icon name="layers" :size="15" />
                            <span>Reportes custom</span>
                        </a>
                    @endcan
                </div>

                <div class="sb-group">
                    <div class="sb-group-title">Datos</div>
                    @can('catalogos.gestionar', $proyectoActivo->id)
                        <a href="{{ route('proyectos.catalogos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.catalogos')) active @endif">
                            <x-ui.icon name="tag" :size="15" />
                            <span>Catálogos</span>
                        </a>
                        <a href="{{ route('proyectos.carteras', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.carteras')) active @endif">
                            <x-ui.icon name="folder" :size="15" />
                            <span>Carteras</span>
                        </a>
                    @endcan
                    @can('importaciones.crear', $proyectoActivo->id)
                        <a href="{{ route('proyectos.importaciones', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.importaciones*')) active @endif">
                            <x-ui.icon name="upload" :size="15" />
                            <span>Importaciones</span>
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
                </div>

                <div class="sb-group">
                    <div class="sb-group-title">Trazabilidad</div>
                    @can('auditoria.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.auditoria', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.auditoria*')) active @endif">
                            <x-ui.icon name="shield" :size="15" />
                            <span>Auditoría</span>
                        </a>
                    @endcan
                    @can('notificaciones.ver', $proyectoActivo->id)
                        <a href="{{ route('proyectos.notificaciones', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.notificaciones')) active @endif">
                            <x-ui.icon name="bell" :size="15" />
                            <span>Notificaciones</span>
                        </a>
                    @endcan
                </div>

                @can('usuarios.gestionar', $proyectoActivo->id)
                    <div class="sb-group">
                        <div class="sb-group-title">Supervisión</div>
                        <a href="{{ route('proyectos.usuarios', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.usuarios')) active @endif">
                            <x-ui.icon name="user" :size="15" />
                            <span>Usuarios del Proyecto</span>
                        </a>
                    </div>
                @endcan

                @can('roles.gestionar', $proyectoActivo->id)
                    <div class="sb-group">
                        <div class="sb-group-title">Permisos</div>
                        <a href="{{ route('proyectos.admin.roles-custom', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.admin.roles-custom')) active @endif">
                            <x-ui.icon name="shield" :size="15" />
                            <span>Roles custom</span>
                        </a>
                        <a href="{{ route('proyectos.admin.matriz-permisos', ['proyecto_id' => $proyectoActivo->id]) }}" wire:navigate
                           class="sb-item @if($rid('proyectos.admin.matriz-permisos')) active @endif">
                            <x-ui.icon name="hash" :size="15" />
                            <span>Matriz de permisos</span>
                        </a>
                    </div>
                @endcan
            @endif

            @if($esAdmin)
                <div class="sb-group">
                    <div class="sb-group-title">Administración</div>
                    <a href="{{ route('admin.dashboard') }}" wire:navigate
                       class="sb-item @if($rid('admin.dashboard')) active @endif">
                        <x-ui.icon name="bar-chart" :size="15" />
                        <span>Panel Admin</span>
                    </a>
                    <a href="{{ route('admin.proyectos') }}" wire:navigate
                       class="sb-item @if($rid('admin.proyectos')) active @endif">
                        <x-ui.icon name="folder" :size="15" />
                        <span>Proyectos</span>
                    </a>
                    <a href="{{ route('admin.mandantes') }}" wire:navigate
                       class="sb-item @if($rid('admin.mandantes')) active @endif">
                        <x-ui.icon name="building" :size="15" />
                        <span>Mandantes</span>
                    </a>
                    <a href="{{ route('admin.usuarios') }}" wire:navigate
                       class="sb-item @if($rid('admin.usuarios')) active @endif">
                        <x-ui.icon name="users" :size="15" />
                        <span>Usuarios</span>
                    </a>
                    <a href="{{ route('admin.campos-personalizados') }}" wire:navigate
                       class="sb-item @if($rid('admin.campos-personalizados')) active @endif">
                        <x-ui.icon name="hash" :size="15" />
                        <span>Campos Personalizados</span>
                    </a>
                    <a href="{{ route('admin.entidades-configurables') }}" wire:navigate
                       class="sb-item @if($rid('admin.entidades-configurables')) active @endif">
                        <x-ui.icon name="layers" :size="15" />
                        <span>Entidades Configurables</span>
                    </a>
                    <a href="{{ route('admin.auditoria') }}" wire:navigate
                       class="sb-item @if($rid('admin.auditoria')) active @endif">
                        <x-ui.icon name="shield" :size="15" />
                        <span>Auditoría global</span>
                    </a>
                </div>
            @endif

            @if(!$proyectoActivo && !$esAdmin)
                <div class="sb-group">
                    <div class="sb-group-title">Inicio</div>
                    <a href="{{ route('dashboard') }}" wire:navigate
                       class="sb-item @if($rid('dashboard')) active @endif">
                        <x-ui.icon name="folder" :size="15" />
                        <span>Mis Proyectos</span>
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
