<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div style="font-size:12px;color:var(--text-tertiary);">
            Lectura. Cada fila es un permiso, cada columna un rol.
        </div>
        <div class="flex items-center gap-2">
            <label class="field-label" style="margin:0;">Grupo</label>
            <select wire:model.live="filtroGrupo" class="select" style="min-width:160px;">
                <option value="">— todos —</option>
                @foreach($grupos as $g)
                    <option value="{{ $g }}">{{ $g }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card" style="overflow-x:auto;">
        <table class="table-compact" style="font-size:12px;">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;background:var(--surface);min-width:280px;">Permiso</th>
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
                        <td style="position:sticky;left:0;background:var(--surface);">
                            <div class="font-mono" style="color:var(--text-tertiary);font-size:11px;">{{ $p->grupo }}</div>
                            <div style="font-weight:600;">{{ $p->codigo }}</div>
                            <div style="color:var(--text-secondary);font-size:11px;">{{ $p->nombre }}</div>
                        </td>
                        @foreach($rolesBase as $r)
                            <td style="text-align:center;">
                                @if(in_array((int) $p->id, $rolPermisoBase->get($r->id, []), true))
                                    <x-ui.icon name="check" :size="14" />
                                @else
                                    <span style="color:var(--text-tertiary);">·</span>
                                @endif
                            </td>
                        @endforeach
                        @foreach($rolesCustom as $rc)
                            <td style="text-align:center;">
                                @if(in_array((int) $p->id, $rolPermisoCustom->get($rc->id, []), true))
                                    <x-ui.icon name="check" :size="14" />
                                @else
                                    <span style="color:var(--text-tertiary);">·</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
