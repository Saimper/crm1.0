<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div class="text-sm text-ink-500">
            {{ __('usuarios.matrix_read_hint') }}
        </div>
        <div class="flex items-center gap-2">
            <label class="field-label" style="margin:0;">{{ __('usuarios.label_group') }}</label>
            <select wire:model.live="filtroGrupo" class="select" style="min-width:160px;">
                <option value="">{{ __('usuarios.option_all_groups') }}</option>
                @foreach($grupos as $g)
                    <option value="{{ $g }}">{{ $g }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card scroll-x">
        {{-- Pegada al borde superior de la tarjeta, como en el resto de listados:
             suelta entre dos bloques parecía un separador. --}}
        <x-ui.cargando />

        <table class="table-compact text-sm">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;background:var(--bg-elev);min-width:280px;">{{ __('usuarios.col_permission') }}</th>
                    @foreach($rolesBase as $r)
                        <th style="text-align:center;min-width:80px;">
                            <span class="badge badge-neutral">{{ $r->codigo }}</span>
                        </th>
                    @endforeach
                    @foreach($rolesCustom as $rc)
                        <th style="text-align:center;min-width:80px;">
                            <span class="badge badge-primary code-mono">{{ $rc->codigo }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($permisos as $p)
                    <tr>
                        <td style="position:sticky;left:0;background:var(--bg-elev);">
                            <div class="font-mono text-ink-500 text-xs">{{ $p->grupo }}</div>
                            <div class="font-semibold">{{ $p->codigo }}</div>
                            <div class="text-ink-600 text-xs">{{ $p->nombre }}</div>
                        </td>
                        @foreach($rolesBase as $r)
                            <td style="text-align:center;">
                                @if(in_array((int) $p->id, $rolPermisoBase->get($r->id, []), true))
                                    <x-ui.icon name="check" :size="14" />
                                @else
                                    <span class="text-ink-500">·</span>
                                @endif
                            </td>
                        @endforeach
                        @foreach($rolesCustom as $rc)
                            <td style="text-align:center;">
                                @if(in_array((int) $p->id, $rolPermisoCustom->get($rc->id, []), true))
                                    <x-ui.icon name="check" :size="14" />
                                @else
                                    <span class="text-ink-500">·</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
