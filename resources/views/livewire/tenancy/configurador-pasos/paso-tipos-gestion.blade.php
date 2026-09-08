<div>
    @if(session('paso-tipos-gestion-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-tipos-gestion-ok') }}</div>
    @endif
    @if(session('paso-tipos-gestion-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-tipos-gestion-error') }}</div>
    @endif

    {{-- Canales. Van aquí porque la cascada de la Vista de Trabajo empieza en el
         canal y sigue por el tipo: se configuran juntos porque se usan juntos.
         El catálogo de canales es global; esto dice qué hace este proyecto con
         él. --}}
    <div class="card" style="margin-bottom:14px;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
            <strong class="text-base">{{ __('configurador.canales.titulo') }}</strong>
            <div class="text-sm text-ink-500" style="margin-top:2px;">{{ __('configurador.canales.ayuda') }}</div>
        </div>
        <table class="table table-compact">
            <thead>
                <tr>
                    <th style="width:60px;">{{ __('configurador.campo_orden') }}</th>
                    <th style="width:150px;">{{ __('configurador.campo_codigo') }}</th>
                    <th>{{ __('configurador.canales.col_nombre') }}</th>
                    <th style="width:120px;">{{ __('configurador.canales.col_duracion') }}</th>
                    <th style="width:120px;">{{ __('configurador.canales.col_adjunto') }}</th>
                    <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($canales as $i => $canal)
                    <tr wire:key="canal-{{ $canal->canal_id }}">
                        <td>
                            <button type="button" wire:click="moverCanal({{ $canal->canal_id }}, -1)" class="btn btn-ghost btn-sm"
                                    style="padding:0 3px;{{ $i === 0 ? 'visibility:hidden;' : '' }}" aria-label="{{ __('common.move_up') }}">↑</button>
                            <button type="button" wire:click="moverCanal({{ $canal->canal_id }}, 1)" class="btn btn-ghost btn-sm"
                                    style="padding:0 3px;{{ $i === $canales->count() - 1 ? 'visibility:hidden;' : '' }}" aria-label="{{ __('common.move_down') }}">↓</button>
                        </td>
                        <td><span class="font-mono text-sm">{{ $canal->codigo }}</span></td>
                        <td>
                            <input type="text" class="input" style="height:28px;"
                                   value="{{ $canal->etiqueta ?? $canal->nombre_global }}"
                                   placeholder="{{ $canal->nombre_global }}"
                                   wire:change="renombrarCanal({{ $canal->canal_id }}, $event.target.value)"/>
                        </td>
                        <td>
                            <label class="text-sm" style="display:inline-flex;align-items:center;gap:6px;">
                                <input type="checkbox" @checked($canal->requiere_duracion)
                                       wire:click="alternarBanderaCanal({{ $canal->canal_id }}, 'requiere_duracion')"/>
                            </label>
                        </td>
                        <td>
                            <label class="text-sm" style="display:inline-flex;align-items:center;gap:6px;">
                                <input type="checkbox" @checked($canal->permite_adjunto)
                                       wire:click="alternarBanderaCanal({{ $canal->canal_id }}, 'permite_adjunto')"/>
                            </label>
                        </td>
                        <td>
                            <button type="button" wire:click="alternarCanal({{ $canal->canal_id }})" class="btn btn-ghost btn-sm">
                                <span class="dot dot-{{ $canal->activo ? 'success' : 'neutral' }}"></span>
                                {{ $canal->activo ? __('configurador.activo') : __('configurador.inactivo') }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <x-ui.toolbar :count="__('configurador.tipos_gestion.n_tipos', ['n' => $tipos->count()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda"
                               placeholder="{{ __('common.search') }}…" />
            <x-slot:acciones>
                <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                    <x-ui.icon name="plus" :size="14" />
                    <span>{{ __('configurador.tipos_gestion.nuevo') }}</span>
                </button>
            </x-slot:acciones>
        </x-ui.toolbar>

        {{-- La tabla de antes sigue en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($tipos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('configurador.tipos_gestion.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.tipos_gestion.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th class="num" style="width:70px;">{{ __('configurador.campo_orden') }}</th>
                        <th style="width:110px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tipos as $t)
                        <tr wire:key="paso-tipo-{{ $t->id }}" wire:click="abrirFormEditar({{ $t->id }})">
                            <td><span class="font-mono text-sm">{{ $t->codigo }}</span></td>
                            <td><span class="font-medium">{{ $t->nombre }}</span></td>
                            <td class="num">{{ $t->orden }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $t->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $t->activo ? __('configurador.activo') : __('configurador.inactivo') }}
                                </span>
                            </td>
                            <td class="text-ink-400"><x-ui.icon name="chevron-right" :size="14" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-tipo-scrim"></div>
        <div class="drawer" wire:key="paso-tipo-drawer">
            <div class="drawer-header">
                <div class="text-md font-semibold">
                    {{ $editandoId === null ? __('configurador.tipos_gestion.drawer_nuevo') : __('configurador.tipos_gestion.drawer_editar') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('configurador.cerrar') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="LLAMADA" maxlength="50"
                               class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('common.name') }}</label>
                        <input type="text" wire:model="form.nombre" maxlength="150"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="field-label">{{ __('configurador.campo_orden') }}</label>
                            <input type="number" min="0" wire:model="form.orden"
                                   class="input @error('form.orden') input-error @enderror"/>
                            @error('form.orden')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="field-label">{{ __('configurador.campo_estado') }}</label>
                            <label class="flex items-center gap-2" style="padding-top:8px;">
                                <input type="checkbox" wire:model="form.activo"/>
                                <span class="text-base text-ink-600">{{ __('configurador.activo') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null)
                    <button type="button"
                            wire:click="eliminar({{ $editandoId }})"
                            wire:confirm="{{ __('configurador.tipos_gestion.confirm_eliminar') }}"
                            class="btn btn-ghost"
                            style="color:var(--danger-text);margin-right:auto;">
                        {{ __('common.delete') }}
                    </button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardar" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
