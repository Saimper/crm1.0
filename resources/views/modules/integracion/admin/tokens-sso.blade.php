<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Tokens SSO</h1>
            <div class="page-subtitle">
                <strong>{{ $resumen['vigentes'] }}</strong> vigentes ·
                {{ $resumen['consumidos'] }} consumidos ·
                <span style="color:var(--danger);">{{ $resumen['expirados'] }} expirados</span>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">← Panel admin</a>
        </div>
    </div>

    @if(session('admin-tokens-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('admin-tokens-ok') }}</div>
    @endif

    <div class="card" style="padding:0;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;">
            <select wire:model.live="estado" class="input" style="width:160px;">
                <option value="">Todos los estados</option>
                <option value="vigentes">Vigentes</option>
                <option value="consumidos">Consumidos</option>
                <option value="expirados">Expirados</option>
            </select>
            <span style="flex:1;"></span>
            <span style="font-size:12px;color:var(--text-tertiary);">{{ $tokens->total() }} resultados</span>
        </div>

        @if($tokens->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="shield" :size="32" /></div>
                <div class="empty-title">Sin tokens</div>
                <div class="empty-desc">Aún no se han emitido tokens SSO con este filtro.</div>
            </div>
        @else
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th style="width:160px;">Emitido</th>
                        <th>Usuario</th>
                        <th>Proyecto</th>
                        <th>IP origen</th>
                        <th style="width:140px;">Expira</th>
                        <th style="width:160px;">Estado</th>
                        <th style="width:100px;text-align:right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokens as $t)
                        @php
                            $consumido = $t->consumido_en !== null;
                            $expirado = ! $consumido && \Illuminate\Support\Carbon::parse($t->expira_en) <= $ahora;
                            $vigente = ! $consumido && ! $expirado;
                            $tone = $consumido ? 'neutral' : ($expirado ? 'danger' : 'success');
                            $estadoTexto = $consumido
                                ? ('Consumido '.\Illuminate\Support\Carbon::parse($t->consumido_en)->format('d/m H:i'))
                                : ($expirado ? 'Expirado' : 'Vigente');
                        @endphp
                        <tr>
                            <td style="font-size:12px;">{{ \Illuminate\Support\Carbon::parse($t->creado_en)->format('d/m/Y H:i') }}</td>
                            <td style="font-size:12px;">
                                <div style="font-weight:500;">{{ $t->usuario_nombre ?? '—' }}</div>
                                <div style="color:var(--text-tertiary);font-size:11px;">{{ $t->usuario_email ?? '' }}</div>
                            </td>
                            <td style="font-size:12px;">
                                @if($t->proyecto_codigo)
                                    <span class="font-mono">{{ $t->proyecto_codigo }}</span>
                                @else
                                    <span style="color:var(--text-tertiary);font-style:italic;">sin proyecto</span>
                                @endif
                            </td>
                            <td style="font-size:11px;font-family:monospace;color:var(--text-secondary);">{{ $t->ip_origen ?? '—' }}</td>
                            <td style="font-size:12px;">{{ \Illuminate\Support\Carbon::parse($t->expira_en)->format('d/m H:i') }}</td>
                            <td>
                                <x-ui.badge :tone="$tone" size="sm">{{ $estadoTexto }}</x-ui.badge>
                            </td>
                            <td style="text-align:right;">
                                @if($vigente)
                                    <button type="button" wire:click="revocar({{ $t->id }})"
                                            wire:confirm="¿Revocar este token? El usuario no podrá usarlo."
                                            class="btn btn-ghost btn-sm" style="color:var(--danger-text);">
                                        Revocar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding:10px 16px;border-top:1px solid var(--border);">
                {{ $tokens->links() }}
            </div>
        @endif
    </div>
</div>
