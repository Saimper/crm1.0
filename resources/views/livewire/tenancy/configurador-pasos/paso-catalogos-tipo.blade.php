<div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
        <span class="label-xs">Catálogos del tipo</span>
        <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);text-transform:uppercase;">
            {{ $proyecto->tipo_operacion }}
        </span>
    </div>

    <div role="tablist"
         style="display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid var(--border);margin-bottom:14px;">
        @foreach($aplicables as $codigo)
            @php $completo = $estados[$codigo] ?? false; $esActiva = $codigo === $tabActiva; @endphp
            <button type="button"
                    role="tab"
                    wire:key="tab-{{ $codigo }}"
                    wire:click="cambiarTab('{{ $codigo }}')"
                    style="
                        display:inline-flex;align-items:center;gap:6px;
                        padding:8px 12px;border:0;background:transparent;cursor:pointer;
                        border-bottom:2px solid {{ $esActiva ? 'var(--brand,#2563eb)' : 'transparent' }};
                        color:{{ $esActiva ? 'var(--text)' : 'var(--text-secondary)' }};
                        font-weight:{{ $esActiva ? 600 : 400 }};
                        font-size:13px;
                    ">
                <span style="
                    width:16px;height:16px;border-radius:999px;display:inline-flex;
                    align-items:center;justify-content:center;font-size:10px;font-weight:600;
                    @if($completo) background:#15803d;color:#ffffff;
                    @else background:var(--bg-subtle);color:var(--text-muted);border:1px solid var(--border);
                    @endif
                ">
                    @if($completo)
                        <x-ui.icon name="check" :size="12" :stroke="3" style="color:#ffffff !important;" />
                    @else
                        ·
                    @endif
                </span>
                <span>{{ $metadata[$codigo]['etiqueta'] }}</span>
            </button>
        @endforeach
    </div>

    @if($aliasActivo !== null)
        <livewire:dynamic-component :is="$aliasActivo" :proyecto="$proyecto" :key="'subcat-'.$tabActiva.'-'.$proyecto->id"/>
    @else
        <div class="empty">
            <div class="empty-title">Tipo de proyecto sin catálogos específicos</div>
        </div>
    @endif
</div>
