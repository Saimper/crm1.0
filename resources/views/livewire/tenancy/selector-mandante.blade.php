<div style="max-width:640px;margin:48px auto;padding:0 20px;">
    <h1 style="font-size:22px;font-weight:600;margin:0 0 6px;">{{ __('tenancy.selector_mandante.titulo') }}</h1>
    <p style="color:var(--text-secondary);margin:0 0 24px;font-size:14px;">
        {{ __('tenancy.selector_mandante.ayuda') }}
    </p>

    @if($mandantes->isEmpty())
        <div style="border:1px solid var(--border);border-radius:8px;padding:20px;color:var(--text-secondary);font-size:14px;">
            {{ __('tenancy.selector_mandante.sin_acceso') }}
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($mandantes as $m)
                <button type="button" wire:click="seleccionar({{ $m->id }})"
                        @class(['selector-mandante-item'])
                        style="display:flex;align-items:baseline;gap:12px;text-align:left;width:100%;
                               border:1px solid {{ (int) $activoId === (int) $m->id ? 'var(--primary)' : 'var(--border)' }};
                               border-radius:8px;padding:14px 16px;background:var(--bg-elev);cursor:pointer;">
                    <span style="font-weight:600;font-size:14px;">{{ $m->nombre }}</span>
                    <span style="font-family:ui-monospace,monospace;font-size:12px;color:var(--text-secondary);">{{ $m->codigo }}</span>
                    @if((int) $activoId === (int) $m->id)
                        <span style="margin-left:auto;font-size:12px;color:var(--primary);">{{ __('tenancy.selector_mandante.activo') }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>
