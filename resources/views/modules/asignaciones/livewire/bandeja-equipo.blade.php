<div class="space-y-4">
    <x-ui.card padding="p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
            <div>
                <label class="block text-xs font-medium text-ink-700">Equipo</label>
                <select wire:model.live="equipoId" class="mt-1 block w-full border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Selecciona…</option>
                    @foreach($equipos as $e)
                        <option value="{{ $e->id }}">{{ $e->nombre }} ({{ $e->codigo }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Miembro</label>
                <select wire:model.live="miembroId" class="mt-1 block w-full border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500"
                        @if($miembros->isEmpty()) disabled @endif>
                    <option value="">Todos</option>
                    @foreach($miembros as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">Estado</label>
                <select wire:model.live="estadoFiltro" class="mt-1 block w-full border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="todos">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_trabajo">En trabajo</option>
                    <option value="cerrada">Cerrada</option>
                </select>
            </div>
            <div class="relative">
                <label class="block text-xs font-medium text-ink-700">Buscar</label>
                <span class="absolute top-[2.1rem] left-3 flex items-center text-ink-400 pointer-events-none">
                    <x-ui.icon name="search" class="w-4 h-4" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       placeholder="Identificación o nombre"
                       class="mt-1 block w-full border-surface-border rounded-lg pl-9 text-sm focus:border-brand-500 focus:ring-brand-500"/>
            </div>
        </div>
    </x-ui.card>

    @if($equipoId === null)
        <x-ui.empty-state title="Selecciona un equipo"
                          message="Elige un equipo arriba para ver la bandeja agregada." />
    @elseif($miembros->isEmpty())
        <x-ui.alert tone="warning" title="Equipo sin miembros">
            El equipo seleccionado aún no tiene miembros activos.
        </x-ui.alert>
    @else
        @if(! $conteoPorMiembro->isEmpty())
            <div>
                <x-ui.section-title title="KPIs por miembro" />
                <x-ui.table>
                    <x-slot name="head">
                        <x-ui.th>Miembro</x-ui.th>
                        <x-ui.th align="right">Pendiente</x-ui.th>
                        <x-ui.th align="right">En trabajo</x-ui.th>
                        <x-ui.th align="right">Cerrada</x-ui.th>
                        <x-ui.th align="right">Total</x-ui.th>
                    </x-slot>
                    @php $porMiembro = $conteoPorMiembro->groupBy('id'); @endphp
                    @foreach($miembros as $m)
                        @php
                            $filas = $porMiembro->get($m->id, collect());
                            $pend = (int) ($filas->firstWhere('estado', 'pendiente')->total ?? 0);
                            $enTr = (int) ($filas->firstWhere('estado', 'en_trabajo')->total ?? 0);
                            $cer  = (int) ($filas->firstWhere('estado', 'cerrada')->total ?? 0);
                            $tot  = $pend + $enTr + $cer;
                        @endphp
                        <tr>
                            <x-ui.td>{{ $m->name }}</x-ui.td>
                            <x-ui.td align="right" mono class="text-warning-700">{{ number_format($pend) }}</x-ui.td>
                            <x-ui.td align="right" mono class="text-info-700">{{ number_format($enTr) }}</x-ui.td>
                            <x-ui.td align="right" mono class="text-success-700">{{ number_format($cer) }}</x-ui.td>
                            <x-ui.td align="right" mono class="font-semibold">{{ number_format($tot) }}</x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif

        <div>
            <x-ui.section-title title="Asignaciones filtradas"
                                hint="Pendientes: {{ $conteoPorEstado['pendiente'] ?? 0 }} · En trabajo: {{ $conteoPorEstado['en_trabajo'] ?? 0 }} · Cerradas: {{ $conteoPorEstado['cerrada'] ?? 0 }}" />

            @if($asignaciones->isEmpty())
                <x-ui.empty-state title="Sin resultados"
                                  message="No hay asignaciones con estos filtros." />
            @else
                <x-ui.table>
                    <x-slot name="head">
                        <x-ui.th>Gestor</x-ui.th>
                        <x-ui.th>Persona</x-ui.th>
                        <x-ui.th>Cartera</x-ui.th>
                        <x-ui.th>Tipo</x-ui.th>
                        <x-ui.th>Estado caso</x-ui.th>
                        <x-ui.th>Último resultado</x-ui.th>
                        <x-ui.th>Estado asig.</x-ui.th>
                        <x-ui.th align="right">Prioridad</x-ui.th>
                        <x-ui.th>Última gestión</x-ui.th>
                    </x-slot>

                    @foreach($asignaciones as $a)
                        @php
                            $nombre = $a->tipo_persona === 'juridica'
                                ? ($a->razon_social ?? '')
                                : trim(($a->nombres ?? '').' '.($a->apellidos ?? ''));
                            $asigTone = match ($a->estado) {
                                'pendiente'  => 'warning',
                                'en_trabajo' => 'info',
                                'cerrada'    => 'success',
                                default      => 'neutral',
                            };
                        @endphp
                        <tr>
                            <x-ui.td>{{ $a->gestor_nombre }}</x-ui.td>
                            <x-ui.td>
                                <div class="text-sm text-ink-900">{{ $nombre }}</div>
                                <div class="text-[11px] text-ink-500 font-mono">{{ $a->identificacion }}</div>
                            </x-ui.td>
                            <x-ui.td>{{ $a->cartera_nombre }}</x-ui.td>
                            <x-ui.td mono>{{ $a->tipo_caso }}</x-ui.td>
                            <x-ui.td>{{ $a->estado_caso_nombre }}</x-ui.td>
                            <x-ui.td>
                                <span class="text-ink-500">{{ $a->resultado_ultimo ?? '—' }}</span>
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.badge :tone="$asigTone">{{ $a->estado }}</x-ui.badge>
                            </x-ui.td>
                            <x-ui.td align="right" mono>{{ $a->prioridad }}</x-ui.td>
                            <x-ui.td>
                                {{ $a->fecha_ultima_gestion ? \Illuminate\Support\Carbon::parse($a->fecha_ultima_gestion)->format('d/m/Y H:i') : '—' }}
                            </x-ui.td>
                        </tr>
                    @endforeach

                    <x-slot name="footer">
                        {{ $asignaciones->links() }}
                    </x-slot>
                </x-ui.table>
            @endif
        </div>
    @endif
</div>
