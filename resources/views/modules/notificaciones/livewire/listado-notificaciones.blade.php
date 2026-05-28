<div class="space-y-4">
    <x-ui.card padding="p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 text-sm">
                <span class="text-ink-700">{{ __('notificaciones.filter_label') }}</span>
                <select wire:model.live="filtro" class="border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="no_leidas">{{ __('notificaciones.filter_unread') }}</option>
                    <option value="todas">{{ __('notificaciones.filter_all') }}</option>
                </select>
                <span class="text-xs text-ink-500">
                    {{ __('notificaciones.unread_count', ['count' => $totalNoLeidas]) }}
                </span>
            </div>
            @if($totalNoLeidas > 0)
                <x-ui.button variant="primary" size="sm" wire:click="marcarTodasLeidas">
                    {{ __('notificaciones.btn_mark_all_read') }}
                </x-ui.button>
            @endif
        </div>
    </x-ui.card>

    @if($notificaciones->isEmpty())
        <x-ui.empty-state
            :title="__('notificaciones.empty_title')"
            :message="__('notificaciones.empty_message')" />
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
                    @php
                        $ruta = $rutas[$n->id] ?? null;
                        $linkUrl = $ruta !== null
                            ? route('proyectos.trabajo', [
                                'proyecto_id' => app('tenancy.proyecto_activo')->id,
                                'persona' => $ruta['persona_public_id'],
                                'caso' => $ruta['caso_public_id'],
                            ])
                            : null;
                    @endphp
                    <li class="p-4 flex items-start gap-3 {{ $esLeida ? 'bg-white' : 'bg-brand-50/40' }}">
                        <div class="flex-shrink-0 mt-1">
                            <span class="inline-block h-2 w-2 rounded-full {{ $esLeida ? 'bg-ink-400/40' : $dotTone }}"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-4">
                                @if($linkUrl)
                                    <a href="{{ $linkUrl }}" wire:navigate class="text-sm font-semibold text-ink-900 hover:text-brand-700 hover:underline">
                                        {{ $n->titulo }}
                                    </a>
                                @else
                                    <div class="text-sm font-semibold text-ink-900">{{ $n->titulo }}</div>
                                @endif
                                <div class="text-xs text-ink-500 whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::parse($n->creada_en)->diffForHumans() }}
                                </div>
                            </div>
                            <div class="text-sm text-ink-700 mt-1">{{ $n->mensaje }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <x-ui.badge :tone="$tipoTone" size="sm">{{ $n->tipo }}</x-ui.badge>
                                @if(!empty($meta['caso_id']))
                                    @if($linkUrl)
                                        <a href="{{ $linkUrl }}" wire:navigate class="text-[11px] text-brand-700 hover:underline">
                                            {{ __('notificaciones.link_view_case', ['id' => $meta['caso_id']]) }}
                                        </a>
                                    @else
                                        <span class="text-[11px] text-ink-500">caso #{{ $meta['caso_id'] }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @unless($esLeida)
                            <x-ui.button variant="ghost" size="sm" wire:click="marcarLeida({{ $n->id }})">
                                {{ __('notificaciones.btn_mark_read') }}
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
