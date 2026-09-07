<div style="display:flex;flex-direction:column;gap:14px;">
    @if(session('campanas-ok'))
        <div class="card" style="padding:10px 14px;border-color:var(--success);background:var(--success-soft);color:var(--success-text);font-size:13px;">
            {{ session('campanas-ok') }}
        </div>
    @endif

    {{-- Sin campañas no se puede asignar nada, y sin asignación ningún gestor ve
         su bandeja. Se dice aquí, que es donde se resuelve. --}}
    @if($campanas->isEmpty())
        <div class="card" style="padding:16px;border-color:var(--warning);background:var(--warning-soft);">
            <div style="font-size:13px;font-weight:600;color:var(--warning-text);">{{ __('campanas.vacio_titulo') }}</div>
            <div style="font-size:13px;color:var(--warning-text);margin-top:4px;">{{ __('campanas.vacio_ayuda', ['entidades' => $rotuloCasos]) }}</div>
        </div>
    @endif

    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);">
            <div>
                <div style="font-size:14px;font-weight:600;color:var(--text);">{{ __('campanas.titulo') }}</div>
                <div style="font-size:12px;color:var(--text-tertiary);">
                    {{ trans_choice('campanas.sin_asignar', $casosSinAsignar, ['n' => number_format($casosSinAsignar), 'entidad' => $rotuloCaso, 'entidades' => $rotuloCasos]) }}
                </div>
            </div>
            <button type="button" class="btn btn-primary" wire:click="abrirFormCrear">
                + {{ __('campanas.nueva') }}
            </button>
        </div>

        @if($campanas->isNotEmpty())
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:150px;">{{ __('campanas.col_codigo') }}</th>
                        <th>{{ __('campanas.col_nombre') }}</th>
                        <th style="width:190px;">{{ __('campanas.col_vigencia') }}</th>
                        <th style="width:110px;" class="num">{{ __('campanas.col_asignados') }}</th>
                        <th style="width:220px;">{{ __('campanas.col_estado') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campanas as $c)
                        <tr wire:key="campana-{{ $c->id }}">
                            <td><span class="font-mono" style="font-size:12px;">{{ $c->codigo }}</span></td>
                            <td><span style="font-weight:500;">{{ $c->nombre }}</span></td>
                            <td style="font-size:12px;color:var(--text-secondary);">
                                {{ \Illuminate\Support\Carbon::parse($c->fecha_inicio)->format('d/m/Y') }}
                                → {{ $c->fecha_fin ? \Illuminate\Support\Carbon::parse($c->fecha_fin)->format('d/m/Y') : '∞' }}
                            </td>
                            <td class="num">{{ number_format($c->total_asignaciones) }}</td>
                            <td>
                                <select wire:change="cambiarEstado({{ $c->id }}, $event.target.value)"
                                        class="select" style="font-size:12px;padding:4px 8px;height:auto;">
                                    @foreach($estados as $e)
                                        <option value="{{ $e->value }}" @selected($c->estado === $e->value)>
                                            {{ __('campanas.estado_'.$e->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($formVisible)
        <div class="drawer-backdrop" wire:click="cerrarForm"></div>
        <div class="drawer" style="max-width:520px;">
            <div class="drawer-header">
                <span style="font-weight:600;">{{ __('campanas.nueva') }}</span>
                <button type="button" wire:click="cerrarForm" class="icon-btn" aria-label="{{ __('common.cancel') }}">
                    <x-ui.icon name="x" :size="14" />
                </button>
            </div>
            <div class="drawer-body" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="field-label">{{ __('campanas.col_codigo') }}</label>
                    <input type="text" wire:model="form.codigo" placeholder="COBRANZA_SEP"
                           class="input mono uppercase @error('form.codigo') input-error @enderror"/>
                    @error('form.codigo')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('campanas.col_nombre') }}</label>
                    <input type="text" wire:model="form.nombre" class="input @error('form.nombre') input-error @enderror"/>
                    @error('form.nombre')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="field-label">{{ __('campanas.col_descripcion') }}</label>
                    <textarea wire:model="form.descripcion" rows="2" class="input"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="field-label">{{ __('campanas.desde') }}</label>
                        <input type="date" wire:model="form.fecha_inicio" class="input @error('form.fecha_inicio') input-error @enderror"/>
                        @error('form.fecha_inicio')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="field-label">{{ __('campanas.hasta') }}</label>
                        <input type="date" wire:model="form.fecha_fin" class="input @error('form.fecha_fin') input-error @enderror"/>
                        @error('form.fecha_fin')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn btn-ghost" wire:click="cerrarForm">{{ __('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" wire:click="guardar">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
