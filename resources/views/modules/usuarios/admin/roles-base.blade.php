<section class="space-y-4">
    @if(session('roles-base-ok'))<div class="alert alert-success" role="status">{{ session('roles-base-ok') }}</div>@endif
    <div>
        <h2 class="text-base font-semibold">Roles base</h2>
        <p class="text-sm text-ink-500 mt-1">Define lo que puede hacer cada rol. Las carteras autorizadas se asignan a cada usuario; los equipos organizan el trabajo.</p>
    </div>
    <div class="grid gap-3 lg:grid-cols-3">
        @foreach($roles as $rol)
            <article class="card card-pad space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-semibold">{{ $rol->nombre }}</h3>
                    <span class="badge badge-neutral">{{ $rol->codigo }}</span>
                </div>
                <p class="text-sm text-ink-500">{{ $rol->descripcion }}</p>
                <p class="text-xs text-ink-500">{{ $excepciones[$rol->id] ?? 0 }} decisiones propias de este proyecto</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="editar('{{ $rol->codigo }}', 'proyecto')" class="btn btn-secondary btn-sm">Editar en este proyecto</button>
                    <button type="button" wire:click="editar('{{ $rol->codigo }}', 'global')" class="btn btn-ghost btn-sm">Plantilla global</button>
                </div>
            </article>
        @endforeach
    </div>
    @if($codigo !== null)
        <form wire:submit="guardar" class="card" wire:key="base-editor-{{ $codigo }}-{{ $alcance }}">
            <div class="card-header flex-wrap gap-3">
                <div>
                    <h3 class="card-title">{{ $nombre }} · {{ $alcance === 'global' ? 'Plantilla global' : 'Este proyecto' }}</h3>
                    <p class="text-xs text-ink-500 mt-1">{{ $alcance === 'global' ? 'Los cambios alcanzan todos los proyectos que heredan estos permisos.' : 'Cada decisión sustituye la plantilla únicamente para este rol y este proyecto.' }}</p>
                </div>
                <button type="button" wire:click="cerrar" class="btn btn-ghost btn-sm">Cerrar</button>
            </div>
            <div class="card-pad space-y-4">
                @error('configuracion')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                @if($alcance === 'global')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="field"><label for="base-role-name" class="field-label">Nombre</label><input id="base-role-name" wire:model="nombre" class="input" maxlength="100" />@error('nombre')<p class="text-danger-600">{{ $message }}</p>@enderror</div>
                        <div class="field"><label for="base-role-description" class="field-label">Descripción</label><input id="base-role-description" wire:model="descripcion" class="input" maxlength="500" />@error('descripcion')<p class="text-danger-600">{{ $message }}</p>@enderror</div>
                    </div>
                @else
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <p class="text-sm text-ink-500">Heredar sigue la plantilla. Permitir y Denegar se mantienen aunque la plantilla cambie. Si un usuario tiene varios roles, sus permisos permitidos se suman.</p>
                        <button type="button" wire:click="heredarTodo" class="btn btn-ghost btn-sm">Heredar todos</button>
                    </div>
                @endif
                <label class="block"><span class="sr-only">Buscar permisos</span><input type="search" wire:model.live.debounce.250ms="busqueda" class="input" placeholder="Buscar por permiso o módulo…" /></label>
                <x-ui.cargando />
                <div class="space-y-3 max-h-[560px] overflow-y-auto pr-1">
                    @forelse($gruposPermisos as $grupo => $filas)
                        <details open class="rounded-lg border border-ink-200">
                            <summary class="cursor-pointer px-4 py-3 font-semibold bg-ink-50">{{ __('usuarios.groups.'.$grupo) }} <span class="text-ink-500 font-normal">· {{ $filas->count() }}</span></summary>
                            <div class="divide-y divide-ink-100">
                                @foreach($filas as $permiso)
                                    <div class="flex items-center justify-between gap-4 px-4 py-3" wire:key="base-permission-{{ $permiso->id }}">
                                        <label for="base-permission-{{ $permiso->id }}" class="text-sm"><span class="block font-medium">{{ $permiso->nombre }}</span><span class="block text-xs text-ink-500">{{ $permiso->codigo }}</span></label>
                                        @if($alcance === 'global')
                                            <input id="base-permission-{{ $permiso->id }}" type="checkbox" wire:model="permisos" value="{{ $permiso->codigo }}" class="checkbox" />
                                        @else
                                            <select id="base-permission-{{ $permiso->id }}" wire:model="decisiones.{{ $permiso->id }}" class="select w-auto max-w-[220px]">
                                                <option value="heredar">Heredar · {{ in_array($permiso->codigo, $permisos, true) ? 'permitido' : 'sin permiso' }}</option>
                                                <option value="permitir">Permitir en este proyecto</option>
                                                <option value="denegar">Denegar en este proyecto</option>
                                            </select>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <p class="text-sm text-ink-500 py-4">No hay permisos que coincidan con la búsqueda.</p>
                    @endforelse
                </div>
                <p class="text-xs text-ink-500">ADMIN_GLOBAL, los códigos del sistema y los permisos de administración protegidos conservan su función. Los cambios quedan en auditoría.</p>
            </div>
            <div class="card-footer flex justify-end gap-2">
                <button type="button" wire:click="cerrar" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" @if($alcance === 'global') wire:confirm="Guardar esta plantilla cambiará los permisos heredados en todos los proyectos. ¿Continuar?" @endif>{{ $alcance === 'global' ? 'Guardar plantilla global' : 'Guardar en este proyecto' }}</button>
            </div>
        </form>
    @endif
</section>
