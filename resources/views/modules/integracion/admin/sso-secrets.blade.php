<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('integracion.title') }}</h1>
            <div class="page-subtitle">{{ __('integracion.subtitle') }}</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('integracion.back_to_panel') }}</a>
        </div>
    </div>

    @if(session('admin-sso-ok'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('admin-sso-ok') }}</div>
    @endif

    <div class="card" style="padding:0;">
        @if($this->mandantes->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-ui.icon name="shield" :size="32" /></div>
                <div class="empty-title">{{ __('integracion.empty_title') }}</div>
                <div class="empty-desc">{{ __('integracion.empty_desc') }}</div>
            </div>
        @else
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th style="width:60px;">{{ __('integracion.col_id') }}</th>
                        <th>{{ __('integracion.col_mandante') }}</th>
                        <th>{{ __('integracion.col_secret') }}</th>
                        <th style="width:170px;">{{ __('integracion.col_old_secret') }}</th>
                        <th style="width:140px;">{{ __('integracion.col_last_rotation') }}</th>
                        <th style="width:80px;text-align:center;">{{ __('integracion.col_status') }}</th>
                        <th style="width:280px;text-align:right;">{{ __('integracion.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->mandantes as $m)
                        @php
                            $esRevelado = ! empty($revelado[$m->id]);
                            $secretShow = $esRevelado
                                ? (string) $m->sso_secret
                                : str_repeat('•', 12).' '.substr((string) $m->sso_secret, -8);
                            $rotadoAhora = $rotadoId === (int) $m->id;
                            $oldVigente = $m->sso_secret_old !== null
                                && $m->sso_secret_old_expires_at !== null
                                && \Illuminate\Support\Carbon::parse($m->sso_secret_old_expires_at)->isFuture();
                        @endphp
                        <tr @class(['row-highlight' => $rotadoAhora])>
                            <td style="font-family:monospace;font-size:12px;color:var(--text-secondary);">{{ $m->id }}</td>
                            <td style="font-size:12px;">
                                <div style="font-weight:500;">{{ $m->nombre }}</div>
                                <div style="color:var(--text-tertiary);font-size:11px;font-family:monospace;">{{ $m->codigo }}</div>
                            </td>
                            <td style="font-family:monospace;font-size:11px;color:var(--text-secondary);">
                                @if(empty($m->sso_secret))
                                    <span style="color:var(--danger-text);">{{ __('integracion.secret_not_set') }}</span>
                                @else
                                    {{ $secretShow }}
                                    @if($rotadoAhora)
                                        <div style="margin-top:4px;color:var(--success-text);font-size:11px;">
                                            {{ __('integracion.secret_copy_now') }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td style="font-size:11px;color:var(--text-tertiary);">
                                @if($oldVigente)
                                    <span style="color:var(--warning-text);">{{ __('integracion.old_valid_until') }}</span>
                                    <div style="font-family:monospace;">{{ \Illuminate\Support\Carbon::parse($m->sso_secret_old_expires_at)->format('d/m/Y H:i') }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="font-size:11px;color:var(--text-tertiary);">
                                {{ $m->actualizada_en ? \Illuminate\Support\Carbon::parse($m->actualizada_en)->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td style="text-align:center;">
                                <x-ui.badge :tone="$m->activo ? 'success' : 'neutral'" size="sm">
                                    {{ $m->activo ? __('integracion.status_active') : __('integracion.status_inactive') }}
                                </x-ui.badge>
                            </td>
                            <td style="text-align:right;">
                                @if(! empty($m->sso_secret))
                                    @if($esRevelado)
                                        <button type="button" wire:click="ocultar({{ $m->id }})" class="btn btn-ghost btn-sm">
                                            {{ __('integracion.btn_hide') }}
                                        </button>
                                    @else
                                        <button type="button" wire:click="revelar({{ $m->id }})" class="btn btn-ghost btn-sm">
                                            {{ __('integracion.btn_show') }}
                                        </button>
                                    @endif
                                @endif
                                <button type="button"
                                        wire:click="abrirWebhooks({{ $m->id }})"
                                        class="btn btn-ghost btn-sm">
                                    {{ __('integracion.btn_webhooks') }}
                                </button>
                                <button type="button"
                                        wire:click="rotar({{ $m->id }})"
                                        wire:confirm="{{ __('integracion.confirm_rotate', ['codigo' => $m->codigo]) }}"
                                        class="btn btn-ghost btn-sm" style="color:var(--danger-text);">
                                    {{ __('integracion.btn_rotate') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($editandoMandanteId !== null)
        <div class="drawer-backdrop" wire:click="cerrarWebhooks"></div>
        <div class="drawer">
            <div class="drawer-header">
                <h2 class="drawer-title">{{ __('integracion.drawer_webhooks_title') }}</h2>
                <button type="button" wire:click="cerrarWebhooks" class="btn btn-ghost btn-sm">×</button>
            </div>
            <div class="drawer-body">
                <p style="font-size:12px;color:var(--text-secondary);margin-bottom:14px;">
                    {!! __('integracion.webhooks_desc') !!}
                </p>

                <div class="form-row">
                    <label class="form-label">{{ __('integracion.label_url_rotated') }}</label>
                    <input type="url" wire:model="webhookUrlSecretRotated" class="form-input"
                           placeholder="https://wrapper.example.com/api/integracion/secret-rotated">
                    @error('webhookUrlSecretRotated')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-row">
                    <label class="form-label">{{ __('integracion.label_url_status') }}</label>
                    <input type="url" wire:model="webhookUrlStatusChanged" class="form-input"
                           placeholder="https://wrapper.example.com/api/integracion/mandante-status">
                    @error('webhookUrlStatusChanged')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                    <button type="button" wire:click="probarWebhookStatus({{ $editandoMandanteId }})"
                            class="btn btn-ghost btn-sm" style="margin-top:6px;">
                        {{ __('integracion.btn_test_status') }}
                    </button>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" wire:click="cerrarWebhooks" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                <button type="button" wire:click="guardarWebhooks" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
            </div>
        </div>
    @endif
</div>
