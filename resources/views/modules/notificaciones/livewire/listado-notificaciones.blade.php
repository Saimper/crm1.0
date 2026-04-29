<div class="space-y-4">
    <x-ui.card padding="p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 text-sm">
                <span class="text-ink-700">Filtro:</span>
                <select wire:model.live="filtro" class="border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="no_leidas">Solo no leídas</option>
                    <option value="todas">Todas</option>
                </select>
                <span class="text-xs text-ink-500">
                    No leídas: <strong class="text-brand-700">{{ $totalNoLeidas }}</strong>
                </span>
            </div>
            @if($totalNoLeidas > 0)
                <x-ui.button variant="primary" size="sm" wire:click="marcarTodasLeidas">
                    Marcar todas como leídas
                </x-ui.button>
            @endif
        </div>
    </x-ui.card>

    @if($notificaciones->isEmpty())
        <x-ui.empty-state
            title="Sin notificaciones"
            message="No tienes notificaciones en este filtro." />
    @else
        <x-ui.card padding="p-0">
            <ul class="divide-y divide-surface-border">
                @foreach($notificaciones as $n)
                    @php
                        $meta = is_array($n->metadata) ? $n->metadata : json_decode((string) $n->metadata, true);
                        $esLeida = $n->leida_en !== null;
                        $dotTone = match ($n->tipo) {
                            'compromiso_vencido', 'sla_en_riesgo' => 'bg-danger-500',
                            'compromiso_por_vencer'               => 'bg-warning-500',
                            'asignacion_recibida'                 => 'bg-info-500',
                            default                               => 'bg-ink-400',
                        };
                        $tipoTone = match ($n->tipo) {
                            'compromiso_vencido', 'sla_en_riesgo' => 'danger',
                            'compromiso_por_vencer'               => 'warning',
                            'asignacion_recibida'                 => 'info',
                            default                               => 'neutral',
                        };
                    @endphp
                    <li class="p-4 flex items-start gap-3 {{ $esLeida ? 'bg-white' : 'bg-brand-50/40' }}">
                        <div class="flex-shrink-0 mt-1">
                            <span class="inline-block h-2 w-2 rounded-full {{ $esLeida ? 'bg-ink-400/40' : $dotTone }}"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-4">
                                <div class="text-sm font-semibold text-ink-900">{{ $n->titulo }}</div>
                                <div class="text-xs text-ink-500 whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::parse($n->creada_en)->diffForHumans() }}
                                </div>
                            </div>
                            <div class="text-sm text-ink-700 mt-1">{{ $n->mensaje }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <x-ui.badge :tone="$tipoTone" size="sm">{{ $n->tipo }}</x-ui.badge>
                                @if(!empty($meta['caso_id']))
                                    <span class="text-[11px] text-ink-500">caso #{{ $meta['caso_id'] }}</span>
                                @endif
                            </div>
                        </div>
                        @unless($esLeida)
                            <x-ui.button variant="ghost" size="sm" wire:click="marcarLeida({{ $n->id }})">
                                Marcar leída
                            </x-ui.button>
                        @endunless
                    </li>
                @endforeach
            </ul>

            <div class="px-4 py-3 border-t border-surface-border bg-surface-50">
                {{ $notificaciones->links() }}
            </div>
        </x-ui.card>
    @endif
</div>
