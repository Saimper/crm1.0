<div class="space-y-4">
    <x-ui.card padding="p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
            <div>
                <label class="block text-xs font-medium text-ink-700">{{ __('asignaciones.label_team') }}</label>
                <select wire:model.live="equipoId" class="mt-1 block w-full border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('asignaciones.select_placeholder') }}</option>
                    @foreach($equipos as $e)
                        <option value="{{ $e->id }}">{{ $e->nombre }} ({{ $e->codigo }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">{{ __('asignaciones.label_member') }}</label>
                <select wire:model.live="miembroId" class="mt-1 block w-full border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500"
                        @if($miembros->isEmpty()) disabled @endif>
                    <option value="">{{ __('asignaciones.option_all_members') }}</option>
                    @foreach($miembros as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink-700">{{ __('asignaciones.label_status') }}</label>
                <select wire:model.live="estadoFiltro" class="mt-1 block w-full border-surface-border rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="todos">{{ __('asignaciones.option_all_states') }}</option>
                    <option value="pendiente">{{ __('asignaciones.option_pending') }}</option>
                    <option value="en_trabajo">{{ __('asignaciones.option_in_progress') }}</option>
                    <option value="cerrada">{{ __('asignaciones.option_closed') }}</option>
                </select>
            </div>
            <div class="relative">
                <label class="block text-xs font-medium text-ink-700">{{ __('asignaciones.label_search') }}</label>
                <span class="absolute top-[2.1rem] left-3 flex items-center text-ink-400 pointer-events-none">
                    <x-ui.icon name="search" class="w-4 h-4" />
                </span>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       placeholder="{{ __('asignaciones.search_placeholder_team') }}"
                       class="mt-1 block w-full border-surface-border rounded-lg pl-9 text-sm focus:border-brand-500 focus:ring-brand-500"/>
            </div>
        </div>
    </x-ui.card>

    @if($equipoId === null)
        <x-ui.empty-state :title="__('asignaciones.empty_select_team')"
                          :message="__('asignaciones.empty_select_team_msg')" />
    @elseif($miembros->isEmpty())
        <x-ui.alert tone="warning" :title="__('asignaciones.alert_no_members_title')">
            {{ __('asignaciones.alert_no_members_msg') }}
        </x-ui.alert>
    @else
        @if(! $conteoPorMiembro->isEmpty())
            <div>
                <x-ui.section-title :title="__('asignaciones.section_kpis')" />
                <x-ui.table>
                    <x-slot name="head">
                        <x-ui.th>{{ __('asignaciones.col_member') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('asignaciones.col_pending') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('asignaciones.col_in_progress') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('asignaciones.col_closed') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('asignaciones.col_total') }}</x-ui.th>
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
            <x-ui.section-title :title="__('asignaciones.section_filtered')"
                                :hint="__('asignaciones.section_filtered_hint', ['pending' => $conteoPorEstado['pendiente'] ?? 0, 'in_progress' => $conteoPorEstado['en_trabajo'] ?? 0, 'closed' => $conteoPorEstado['cerrada'] ?? 0])" />

            @if($asignaciones->isEmpty())
                <x-ui.empty-state :title="__('asignaciones.empty_no_results')"
                                  :message="__('asignaciones.empty_no_results_msg')" />
            @else
                <x-ui.table>
                    <x-slot name="head">
                        <x-ui.th>{{ __('asignaciones.col_agent') }}</x-ui.th>
                        <x-ui.th>{{ __('asignaciones.col_person_eq') }}</x-ui.th>
                        <x-ui.th>{{ __('asignaciones.col_portfolio_eq') }}</x-ui.th>
                        <x-ui.th>{{ __('asignaciones.col_type_eq') }}</x-ui.th>
                        <x-ui.th>{{ __('asignaciones.col_case_status_eq') }}</x-ui.th>
                        <x-ui.th>{{ __('asignaciones.col_last_result') }}</x-ui.th>
                        <x-ui.th>{{ __('asignaciones.col_assign_status') }}</x-ui.th>
                        <x-ui.th align="right">{{ __('asignaciones.col_priority_eq') }}</x-ui.th>
                        <x-ui.th>{{ __('asignaciones.col_last_management_eq') }}</x-ui.th>
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
                            <x-ui.td align="right" mono>
                                @can('asignaciones.reasignar', $proyectoActivo->id)
                                    <select wire:change="cambiarPrioridad({{ $a->id }}, $event.target.value)"
                                            class="text-xs border-ink-300 rounded font-mono w-12 text-right">
                                        @for($p = 0; $p <= 9; $p++)
                                            <option value="{{ $p }}" @if((int) $a->prioridad === $p) selected @endif>{{ $p }}</option>
                                        @endfor
                                    </select>
                                @else
                                    {{ $a->prioridad }}
                                @endcan
                            </x-ui.td>
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
