<div>
    @if(session('paso-carteras-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('paso-carteras-ok') }}</div>
    @endif

    @if(session('paso-carteras-error'))
        <div class="alert alert-warning" style="margin-bottom:14px;">{{ session('paso-carteras-error') }}</div>
    @endif

    <div class="card">
        <x-ui.toolbar :count="__('configurador.carteras.n_carteras', ['n' => $carteras->count()])">
            <x-ui.search-input wire:model.live.debounce.300ms="busqueda"
                               placeholder="{{ __('common.search') }}…" />
            <x-slot:acciones>
                @if($puedeCrear)
                <button type="button" wire:click="abrirFormCrear" class="btn btn-primary">
                    <x-ui.icon name="plus" :size="14" />
                    <span>{{ __('configurador.carteras.nueva') }}</span>
                </button>
                @endif
            </x-slot:acciones>
        </x-ui.toolbar>

        {{-- La tabla de antes sigue en pantalla mientras llega la nueva; sólo
             esta barra dice que se está trabajando. --}}
        <x-ui.cargando />

        @if($carteras->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                <div class="empty-title">{{ __('configurador.carteras.sin_titulo') }}</div>
                <div class="empty-desc">{{ __('configurador.carteras.sin_desc') }}</div>
            </div>
        @else
            <table class="table table-compact table-clickable">
                <thead>
                    <tr>
                        <th style="width:160px;">{{ __('configurador.campo_codigo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('configurador.campo_descripcion') }}</th>
                        <th class="num" style="width:80px;">{{ __('configurador.carteras.col_casos') }}</th>
                        <th style="width:120px;">{{ __('configurador.campo_estado') }}</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carteras as $c)
                        <tr wire:key="paso-cartera-{{ $c->id }}" @if($puedeEditar) wire:click="abrirFormEditar({{ $c->id }})" @endif>
                            <td><span class="font-mono text-sm">{{ $c->codigo }}</span></td>
                            <td><span class="font-medium">{{ $c->nombre }}</span></td>
                            <td><span class="text-sm text-ink-600">{{ $c->descripcion ?? '—' }}</span></td>
                            <td class="num">{{ $c->total_casos }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span class="dot dot-{{ $c->activo ? 'success' : 'neutral' }}"></span>
                                    {{ $c->activo ? __('configurador.carteras.activa') : __('configurador.carteras.inactiva') }}
                                </span>
                            </td>
                            <td>
                                @if($puedeEliminar)
                                    <button type="button" wire:click.stop="eliminarCartera({{ $c->id }})"
                                            wire:confirm="{{ __('configurador.carteras.confirm_eliminar') }}"
                                            class="btn btn-ghost text-danger-600">{{ __('common.delete') }}</button>
                                @elseif($puedeEditar)
                                    <x-ui.icon name="chevron-right" :size="14" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="scrim" wire:click="cerrarForm" wire:key="paso-cartera-scrim"></div>
        <div class="drawer" wire:key="paso-cartera-drawer">
            <div class="drawer-header">
                <div class="text-md font-semibold">
                    {{ $editandoId === null ? __('configurador.carteras.drawer_nueva') : __('configurador.carteras.drawer_editar') }}
                </div>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('configurador.cerrar') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body">
                <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                    <div>
                        <label class="field-label">{{ __('configurador.campo_codigo') }}</label>
                        <input type="text" wire:model="form.codigo" placeholder="CARTERA_PRINCIPAL"
                               class="input mono uppercase @error('form.codigo') input-error @enderror" maxlength="80"/>
                        @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('common.name') }}</label>
                        <input type="text" wire:model="form.nombre" maxlength="200"
                               class="input @error('form.nombre') input-error @enderror"/>
                        @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('configurador.campo_descripcion') }}</label>
                        <textarea wire:model="form.descripcion" rows="3" maxlength="500"
                                  class="input @error('form.descripcion') input-error @enderror"></textarea>
                        @error('form.descripcion')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.activo"/>
                            <span class="text-base text-ink-600">{{ __('configurador.carteras.cartera_activa') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                @if($editandoId !== null && $puedeEliminar)
                    <button type="button"
                            wire:click="eliminarCartera({{ $editandoId }})"
                            wire:confirm="{{ __('configurador.carteras.confirm_eliminar') }}"
                            class="btn btn-ghost"
                            style="color:var(--danger-text);margin-right:auto;">
                        {{ __('common.delete') }}
                    </button>
                @endif
                <button type="button" wire:click="cerrarForm" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardarCartera" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
