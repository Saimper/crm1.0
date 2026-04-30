<div class="space-y-6">

    @if($esAdminGlobal)
        <div class="card card-pad" style="background:var(--primary-soft);border-color:var(--primary-soft-border);">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="label-xs" style="color:var(--primary-text);">Administrador global</div>
                    <p style="margin-top:4px;font-size:13px;color:var(--primary-text);">
                        Acceso cross-project a administración y reportes consolidados.
                    </p>
                </div>
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-primary">
                    <span>Ir a administración</span>
                    <x-ui.icon name="arrow-right" :size="14" />
                </a>
            </div>
        </div>
    @endif

    <section>
        <x-ui.section-title
            title="Proyectos disponibles"
            :hint="$proyectos->count() . ' ' . ($proyectos->count() === 1 ? 'proyecto' : 'proyectos')" />

        @if($proyectos->isEmpty())
            <div class="card">
                <div class="empty">
                    <div class="empty-icon"><x-ui.icon name="folder" :size="32" /></div>
                    <div class="empty-title">Sin proyectos asignados</div>
                    <div class="empty-desc">Contacta a tu supervisor o al administrador para obtener acceso.</div>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($proyectos as $p)
                    @php
                        $tipoBadge = match ($p->tipo_operacion) {
                            'cobranza' => 'badge-warning',
                            'cx'       => 'badge-primary',
                            'venta'    => 'badge-success',
                            'servicio' => 'badge-neutral',
                            default    => 'badge-neutral',
                        };
                    @endphp
                    <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $p->id]) }}"
                       wire:navigate
                       class="card card-pad proyecto-tile"
                       style="text-decoration:none;color:inherit;display:block;transition:border-color 120ms var(--ease), background 120ms var(--ease);">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="label-xs" style="margin-bottom:2px;">{{ $p->mandante_nombre }}</div>
                                <h3 style="font-weight:600;color:var(--text);font-size:14px;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    {{ $p->nombre }}
                                </h3>
                            </div>
                            <span class="badge {{ $tipoBadge }}">{{ ucfirst($p->tipo_operacion) }}</span>
                        </div>
                        <div class="code-mono" style="margin-top:14px;font-size:11px;color:var(--text-tertiary);">{{ $p->codigo }}</div>
                    </a>
                @endforeach
            </div>
            <style>
                .proyecto-tile:hover { border-color: var(--primary-soft-border); background: var(--bg-subtle); }
            </style>
        @endif
    </section>
</div>
