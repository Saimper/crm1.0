<x-app-layout>
    @php
        /** @var \App\Models\User|null $u */
        $u = auth()->user();
        $esAdminGlobal = $u?->esAdminGlobal() ?? false;

        // D1 / Fase 3: esta ruta va dentro del grupo `mandante.activo`, así que el
        // cliente ya viene resuelto y validado por el middleware. Se lee del
        // contenedor y NUNCA de la sesión ni de la query: el binding es lo único
        // que ya pasó por el resolutor. El `bound()` es defensa en profundidad —
        // si algún día alguien saca el middleware de la ruta, la pantalla degrada
        // a los rótulos antiguos en lugar de reventar con un 500.
        $mandanteActivo = app()->bound('tenancy.mandante_activo')
            ? app('tenancy.mandante_activo')
            : null;

        // Cada tile declara si la pantalla a la que lleva está acotada al cliente
        // activo. No es decorativo: es el mismo reparto que hace routes/web.php.
        //   acotado_al_cliente = true  → ruta bajo el middleware `mandante.activo`
        //                                (proyectos, usuarios, auditoría).
        //   acotado_al_cliente = false → ruta del grupo `admin.global`, que hoy
        //                                alcanza a TODOS los clientes.
        // El dueño reportó justo esta confusión ("el administrador ve todos los
        // usuarios de todas las empresas"): un panel que no dice de qué empresa
        // son los datos invita a operar sobre la fila equivocada. Aquí se dice.
        $tiles = [
            [
                'route' => 'admin.mandantes',
                'title' => __('tenancy.tile_mandantes_title'),
                'desc'  => __('tenancy.tile_mandantes_desc'),
                'icon'  => 'building',
                'solo_admin_global' => true,
                'acotado_al_cliente' => false,
            ],
            [
                'route' => 'admin.proyectos',
                'title' => __('tenancy.tile_proyectos_title'),
                'desc'  => __('tenancy.tile_proyectos_desc'),
                'icon'  => 'folder',
                'solo_admin_global' => false,
                'acotado_al_cliente' => true,
            ],
            [
                'route' => 'admin.usuarios',
                // El título ya no dice "globales" para el ADMIN_GLOBAL: /admin/usuarios
                // corre bajo `mandante.activo`, así que no es una pantalla global para
                // nadie — para los dos roles es la de usuarios de ESTE cliente. Lo que
                // sí cambia con el rol es lo que se puede HACER ahí (crear cuentas y
                // marcar ADMIN_GLOBAL vs solo asignar roles), y eso lo dice la
                // descripción, no el título.
                'title' => __('tenancy.tile_usuarios_mandante_title'),
                'desc'  => $esAdminGlobal
                    ? __('tenancy.tile_usuarios_global_desc')
                    : __('tenancy.tile_usuarios_mandante_desc'),
                'icon'  => 'users',
                'solo_admin_global' => false,
                'acotado_al_cliente' => true,
            ],
            [
                'route' => 'admin.campos-personalizados',
                'title' => __('tenancy.tile_campos_title'),
                'desc'  => __('tenancy.tile_campos_desc'),
                'icon'  => 'hash',
                'solo_admin_global' => true,
                'acotado_al_cliente' => false,
            ],
            [
                'route' => 'admin.entidades-configurables',
                'title' => __('tenancy.tile_entidades_title'),
                'desc'  => __('tenancy.tile_entidades_desc'),
                'icon'  => 'layers',
                'solo_admin_global' => true,
                'acotado_al_cliente' => false,
            ],
            [
                'route' => 'admin.auditoria',
                // Mismo motivo que usuarios: /admin/auditoria está bajo
                // `mandante.activo`, no es "auditoría global" para nadie. Se usa la
                // clave *_mandante_* para los dos roles — y NO `tile_auditoria_title`,
                // que es la del dashboard DE PROYECTO: compartir clave entre dos
                // pantallas distintas hace que renombrar una cambie la otra en silencio.
                'title' => __('tenancy.tile_auditoria_mandante_title'),
                'desc'  => __('tenancy.tile_auditoria_mandante_desc'),
                'icon'  => 'shield',
                'solo_admin_global' => false,
                'acotado_al_cliente' => true,
            ],
        ];

        $tiles = array_filter($tiles, fn ($t) => $esAdminGlobal || ! $t['solo_admin_global']);
    @endphp

    <div class="page">
        <div class="page-header">
            <div>
                {{-- El rótulo dice en qué cliente se está trabajando. Sin esto el
                     ADMIN_GLOBAL leía "Administración global · cross-project" en un
                     panel que ya sólo le enseña un cliente. --}}
                <h1 class="page-title">
                    {{ $mandanteActivo !== null
                        ? __('tenancy.dashboard_title_cliente', ['cliente' => $mandanteActivo->nombre])
                        : ($esAdminGlobal ? __('tenancy.dashboard_title_global') : __('tenancy.dashboard_title_mandante')) }}
                </h1>
                <div class="page-subtitle">
                    @if($mandanteActivo !== null)
                        <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">
                            {{ $mandanteActivo->codigo ?? str_pad((string) $mandanteActivo->id, 4, '0', STR_PAD_LEFT) }}
                        </span>
                        <span>
                            {{ $esAdminGlobal
                                ? __('tenancy.dashboard_subtitle_cliente_global', ['cliente' => $mandanteActivo->nombre])
                                : __('tenancy.dashboard_subtitle_cliente_mandante', ['cliente' => $mandanteActivo->nombre]) }}
                        </span>
                    @else
                        {{ $esAdminGlobal ? __('tenancy.dashboard_subtitle_global') : __('tenancy.dashboard_subtitle_mandante') }}
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('tenancy.back_to_selector') }}</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
            @foreach($tiles as $t)
                @if(\Illuminate\Support\Facades\Route::has($t['route']))
                    <a href="{{ route($t['route']) }}" wire:navigate
                       class="card card-pad admin-tile"
                       style="text-decoration:none;color:inherit;display:block;transition:border-color 120ms var(--ease),background 120ms var(--ease);">
                        <div style="display:flex;align-items:flex-start;gap:12px;">
                            <div style="flex-shrink:0;height:40px;width:40px;border-radius:8px;background:var(--primary-soft);color:var(--primary-text);display:flex;align-items:center;justify-content:center;border:1px solid var(--primary-soft-border);">
                                <x-ui.icon :name="$t['icon']" :size="18" />
                            </div>
                            <div style="min-width:0;">
                                <div style="font-weight:600;color:var(--text);font-size:14px;">{{ $t['title'] }}</div>
                                <p style="margin-top:4px;font-size:12px;color:var(--text-tertiary);line-height:1.5;">{{ $t['desc'] }}</p>
                                {{-- Alcance del destino. La advertencia importa más que
                                     la confirmación: el tile que SÍ cruza clientes es el
                                     que puede hacer daño sin que se note. --}}
                                @if($t['acotado_al_cliente'])
                                    @if($mandanteActivo !== null)
                                        <span class="badge badge-neutral" style="margin-top:8px;">
                                            {{ __('tenancy.alcance_este_cliente', ['cliente' => $mandanteActivo->nombre]) }}
                                        </span>
                                    @endif
                                @else
                                    <span class="badge badge-warning" style="margin-top:8px;"
                                          title="{{ __('tenancy.alcance_todos_los_clientes_ayuda') }}">
                                        {{ __('tenancy.alcance_todos_los_clientes') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>

        <style>
            .admin-tile:hover { border-color: var(--primary-soft-border); background: var(--bg-subtle); }
        </style>
    </div>
</x-app-layout>
