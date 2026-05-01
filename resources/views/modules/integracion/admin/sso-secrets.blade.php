<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">SSO secrets por proyecto</h1>
            <div class="page-subtitle">
                Secret compartido con el wrapper para firmar JWT del handshake (HS256).
                Rotar invalida los tokens en vuelo: coordinar antes con el wrapper.
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">← Panel admin</a>
        </div>
    </div>

    @if(session('admin-sso-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('admin-sso-ok') }}</div>
    @endif

    <div class="card" style="padding:0;">
        @if($this->proyectos->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="shield" :size="32" /></div>
                <div class="empty-title">Sin proyectos</div>
                <div class="empty-desc">Aún no hay proyectos creados.</div>
            </div>
        @else
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>Mandante</th>
                        <th>Proyecto</th>
                        <th>Secret</th>
                        <th style="width:140px;">Última rotación</th>
                        <th style="width:80px;text-align:center;">Estado</th>
                        <th style="width:200px;text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->proyectos as $p)
                        @php
                            $esRevelado = ! empty($revelado[$p->id]);
                            $secretShow = $esRevelado
                                ? (string) $p->sso_secret
                                : str_repeat('•', 12).' '.substr((string) $p->sso_secret, -8);
                            $rotadoAhora = $rotadoId === (int) $p->id;
                        @endphp
                        <tr @class(['row-highlight' => $rotadoAhora])>
                            <td style="font-size:12px;">{{ $p->mandante_codigo ?? '—' }}</td>
                            <td style="font-size:12px;">
                                <div style="font-weight:500;">{{ $p->nombre }}</div>
                                <div style="color:var(--text-tertiary);font-size:11px;font-family:monospace;">{{ $p->codigo }}</div>
                            </td>
                            <td style="font-family:monospace;font-size:11px;color:var(--text-secondary);">
                                @if(empty($p->sso_secret))
                                    <span style="color:var(--danger-text);">— sin configurar —</span>
                                @else
                                    {{ $secretShow }}
                                    @if($rotadoAhora)
                                        <div style="margin-top:4px;color:var(--success-text);font-size:11px;">
                                            ⚠ Cópialo ahora: solo se muestra completo una vez tras rotar.
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td style="font-size:11px;color:var(--text-tertiary);">
                                {{ $p->actualizada_en ? \Illuminate\Support\Carbon::parse($p->actualizada_en)->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td style="text-align:center;">
                                <x-ui.badge :tone="$p->activo ? 'success' : 'neutral'" size="sm">
                                    {{ $p->activo ? 'Activo' : 'Inactivo' }}
                                </x-ui.badge>
                            </td>
                            <td style="text-align:right;">
                                @if(! empty($p->sso_secret))
                                    @if($esRevelado)
                                        <button type="button" wire:click="ocultar({{ $p->id }})" class="btn btn-ghost btn-sm">
                                            Ocultar
                                        </button>
                                    @else
                                        <button type="button" wire:click="revelar({{ $p->id }})" class="btn btn-ghost btn-sm">
                                            Ver
                                        </button>
                                    @endif
                                @endif
                                <button type="button"
                                        wire:click="rotar({{ $p->id }})"
                                        wire:confirm="¿Rotar el secret de {{ $p->codigo }}? El wrapper deberá actualizarse en simultáneo o los handshakes fallarán."
                                        class="btn btn-ghost btn-sm" style="color:var(--danger-text);">
                                    Rotar
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
