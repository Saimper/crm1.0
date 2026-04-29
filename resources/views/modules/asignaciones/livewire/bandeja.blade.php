<div class="space-y-4">
    @if($mensajeExito)
        <x-ui.alert tone="success"
                    x-data="{}" x-init="setTimeout(() => $wire.set('mensajeExito', null), 3000)">
            {{ $mensajeExito }}
        </x-ui.alert>
    @endif

    @error('asignacion')
        <x-ui.alert tone="danger">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card padding="p-4">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $chips = [
                    'todos'      => ['label' => 'Todas',      'count' => $totalGeneral],
                    'pendiente'  => ['label' => 'Pendientes', 'count' => (int) ($conteoPorEstado['pendiente']  ?? 0)],
                    'en_trabajo' => ['label' => 'En trabajo', 'count' => (int) ($conteoPorEstado['en_trabajo'] ?? 0)],
                    'cerrada'    => ['label' => 'Cerradas',   'count' => (int) ($conteoPorEstado['cerrada']    ?? 0)],
                ];
            @endphp
            @foreach($chips as $valor => $chip)
                @php
                    $activo = $estadoFiltro === $valor;
                    $activeClass = $activo
                        ? 'bg-brand-600 text-white border-brand-600 shadow-sm'
                        : 'bg-white text-ink-700 border-surface-border hover:bg-surface-50';
                @endphp
                <button type="button"
                        wire:click="$set('estadoFiltro', '{{ $valor }}')"
                        class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors {{ $activeClass }}">
                    {{ $chip['label'] }}
                    <span class="inline-flex items-center justify-center rounded-full {{ $activo ? 'bg-white/20' : 'bg-surface-100 text-ink-700' }} px-1.5 min-w-[1.25rem]">
                        {{ $chip['count'] }}
                    </span>
                </button>
            @endforeach

            <div class="ml-auto w-full sm:w-80 relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-ink-400 pointer-events-none">
                    <x-ui.icon name="search" class="w-4 h-4" />
                </span>
                <input type="text"
                       wire:model.live.debounce.300ms="busqueda"
                       placeholder="Buscar identificación o nombre..."
                       class="w-full rounded-lg border-surface-border pl-9 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    </x-ui.card>

    @if($asignaciones->isEmpty())
        <x-ui.empty-state
            title="Sin asignaciones"
            message="No hay asignaciones que coincidan con los filtros actuales." />
    @else
        <x-ui.table>
            <x-slot name="head">
                <x-ui.th>Prio</x-ui.th>
                <x-ui.th>Persona</x-ui.th>
                <x-ui.th>Cartera</x-ui.th>
                <x-ui.th>Tipo</x-ui.th>
                <x-ui.th>Estado caso</x-ui.th>
                <x-ui.th>Última gestión</x-ui.th>
                <x-ui.th>Asignación</x-ui.th>
                <x-ui.th align="right">&nbsp;</x-ui.th>
            </x-slot>

            @foreach($asignaciones as $a)
                @php
                    $nombre = $a->tipo_persona === 'juridica'
                        ? (string) $a->razon_social
                        : trim((string) ($a->nombres ?? '').' '.(string) ($a->apellidos ?? ''));
                    $tipoTone = match ($a->tipo_caso) {
                        'cobranza'   => 'warning',
                        'ticket_cx'  => 'info',
                        'lead_venta' => 'success',
                        'servicio'   => 'accent',
                        default      => 'neutral',
                    };
                    $asigTone = match ($a->estado) {
                        'pendiente'  => 'warning',
                        'en_trabajo' => 'info',
                        'cerrada'    => 'success',
                        default      => 'neutral',
                    };
                @endphp
                <tr>
                    <x-ui.td>
                        <x-ui.badge tone="brand" size="sm">{{ $a->prioridad }}</x-ui.badge>
                    </x-ui.td>
                    <x-ui.td>
                        <div class="font-medium text-ink-900">{{ $nombre !== '' ? $nombre : '—' }}</div>
                        <div class="text-xs text-ink-500 font-mono">{{ $a->identificacion }}</div>
                    </x-ui.td>
                    <x-ui.td>
                        <div class="text-xs text-ink-800">{{ $a->cartera_nombre }}</div>
                        @if($a->campana_nombre)
                            <div class="text-[10px] text-ink-500">{{ $a->campana_nombre }}</div>
                        @endif
                    </x-ui.td>
                    <x-ui.td>
                        <x-ui.badge :tone="$tipoTone">
                            {{ ucfirst(str_replace('_', ' ', $a->tipo_caso)) }}
                        </x-ui.badge>
                    </x-ui.td>
                    <x-ui.td>
                        <div class="text-xs text-ink-800">{{ $a->estado_caso_nombre }}</div>
                        @if($a->tiene_compromiso_vigente)
                            <div class="mt-0.5">
                                <x-ui.badge tone="success" size="sm">Compromiso vigente</x-ui.badge>
                            </div>
                        @endif
                    </x-ui.td>
                    <x-ui.td>
                        @if($a->fecha_ultima_gestion)
                            <div class="text-xs text-ink-800">{{ $a->resultado_ultimo ?? '—' }}</div>
                            <div class="text-xs text-ink-500">{{ \Illuminate\Support\Carbon::parse($a->fecha_ultima_gestion)->diffForHumans() }}</div>
                        @else
                            <span class="text-xs text-ink-400">sin gestiones</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td>
                        <x-ui.badge :tone="$asigTone">
                            {{ ucfirst(str_replace('_', ' ', $a->estado)) }}
                        </x-ui.badge>
                    </x-ui.td>
                    <x-ui.td align="right">
                        <div class="flex items-center justify-end gap-2">
                            <x-ui.button
                                as="a"
                                size="sm"
                                :href="route('proyectos.trabajo', ['proyecto_id' => $proyectoActivo->id, 'persona' => $a->persona_public_id, 'caso' => $a->caso_public_id])"
                                wire:navigate>
                                Trabajar
                            </x-ui.button>
                            @if($a->estado !== 'cerrada')
                                <x-ui.button
                                    variant="secondary"
                                    size="sm"
                                    wire:click="cerrarAsignacion({{ $a->id }})"
                                    wire:confirm="¿Cerrar esta asignación? La acción es reversible solo reabriéndola manualmente.">
                                    Cerrar
                                </x-ui.button>
                            @endif
                        </div>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot name="footer">
                {{ $asignaciones->links() }}
            </x-slot>
        </x-ui.table>
    @endif
</div>
