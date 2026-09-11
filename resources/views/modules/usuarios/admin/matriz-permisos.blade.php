<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-base font-semibold">Comparar permisos</h2>
            <p class="text-sm text-ink-500 mt-1">Permisos efectivos en este proyecto, incluidas sus decisiones propias.</p>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="soloDiferencias" class="checkbox" /> Solo diferencias</label>
    </div>
    <div class="grid gap-3 sm:grid-cols-[1fr_220px]">
        <label class="block"><span class="sr-only">Buscar permisos</span><input type="search" wire:model.live.debounce.250ms="busqueda" class="input" placeholder="Buscar permiso o módulo…" /></label>
        <label class="block"><span class="sr-only">Módulo</span><select wire:model.live="filtroGrupo" class="select"><option value="">Todos los módulos</option>@foreach($grupos as $g)<option value="{{ $g }}">{{ __('usuarios.groups.'.$g) }}</option>@endforeach</select></label>
    </div>
    <div class="card overflow-hidden">
        <x-ui.cargando />
        <div class="overflow-auto max-h-[640px]">
            <table class="table table-compact text-sm w-full">
                <caption class="sr-only">Comparación de permisos por rol para el proyecto activo</caption>
                <thead class="sticky top-0 z-20 bg-[var(--bg-elev)]">
                    <tr>
                        <th scope="col" class="sticky left-0 z-30 bg-[var(--bg-elev)] min-w-[240px]">Permisos · {{ $permisos->count() }}</th>
                        @foreach($rolesBase as $rol)
                            <th scope="col" class="min-w-[140px] text-center"><span class="block font-semibold">{{ $rol->nombre }}</span><span class="block text-xs font-normal text-ink-500">Rol base</span></th>
                        @endforeach
                        @foreach($rolesCustom as $rol)
                            <th scope="col" class="min-w-[140px] text-center"><span class="block font-semibold">{{ $rol->nombre }}</span><span class="block text-xs font-normal text-brand-600">Personalizado</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($permisos->groupBy('grupo') as $grupo => $filas)
                        <tr class="bg-ink-50"><th scope="rowgroup" colspan="{{ 1 + $rolesBase->count() + $rolesCustom->count() }}" class="text-left px-3 py-2 border-b border-ink-200 font-semibold text-ink-600">{{ __('usuarios.groups.'.$grupo) }} <span class="font-normal">· {{ $filas->count() }}</span></th></tr>
                        @foreach($filas as $permiso)
                            <tr>
                                <th scope="row" class="sticky left-0 z-10 bg-[var(--bg-elev)] px-3 py-2 border-b border-ink-200 text-left font-normal"><span class="block font-medium" title="{{ $permiso->codigo }}">{{ $permiso->nombre }}</span></th>
                                @foreach($rolesBase as $rol)
                                    <td class="text-center">@if(in_array((int) $permiso->id, $rolPermisoBase->get($rol->id, []), true))<span class="inline-flex items-center gap-1 text-success-700"><x-ui.icon name="check" :size="14" /><span>Permitido</span></span>@else<span class="text-ink-500">Sin permiso</span>@endif</td>
                                @endforeach
                                @foreach($rolesCustom as $rol)
                                    <td class="text-center">@if(in_array((int) $permiso->id, $rolPermisoCustom->get($rol->id, []), true))<span class="inline-flex items-center gap-1 text-success-700"><x-ui.icon name="check" :size="14" /><span>Permitido</span></span>@else<span class="text-ink-500">Sin permiso</span>@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="{{ 1 + $rolesBase->count() + $rolesCustom->count() }}" class="text-center py-8 text-ink-500">No hay permisos que coincidan con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-xs text-ink-500">ADMIN_GLOBAL conserva acceso total. La comparación describe cada rol; los permisos de varios roles se suman y las restricciones por cartera del usuario siguen aplicándose.</div>
    </div>
</section>
