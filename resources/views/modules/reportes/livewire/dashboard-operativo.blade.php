<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700">
                Dashboard operativo · {{ $etiquetaRango }}
            </h3>
            <div class="text-xs text-gray-500">{{ $proyecto->nombre }} · {{ $proyecto->codigo }}</div>
        </div>
        <div class="flex items-center gap-2 text-xs">
            @foreach(['hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Semana', 'mes' => 'Mes'] as $valor => $label)
                <button type="button" wire:click="$set('rango', '{{ $valor }}')"
                        class="px-3 py-1.5 rounded border {{ $rango === $valor ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-gray-500">Cuentas intentadas</div>
            <div class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($cuentasIntentadas) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-gray-500">Cuentas gestionadas</div>
            <div class="text-2xl font-semibold text-emerald-700 mt-1">{{ number_format($cuentasGestionadas) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-gray-500">Efectividad</div>
            <div class="text-2xl font-semibold text-blue-700 mt-1">{{ number_format($efectividad, 1) }}%</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-[10px] uppercase tracking-wider text-gray-500">Total gestiones</div>
            <div class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($totalGestiones) }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-[10px] uppercase tracking-wider text-emerald-700">Compromisos vigentes</div>
            <div class="text-2xl font-semibold text-emerald-900 mt-1">{{ number_format($compromisosVigentes) }}</div>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <div class="text-[10px] uppercase tracking-wider text-red-700">Compromisos vencidos</div>
            <div class="text-2xl font-semibold text-red-900 mt-1">{{ number_format($compromisosVencidos) }}</div>
        </div>
    </div>

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Ranking de gestores ({{ $ranking->count() }})
        </div>
        @if($ranking->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Sin actividad en el rango seleccionado.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Gestor</th>
                        <th class="px-3 py-2 text-right">Gestiones</th>
                        <th class="px-3 py-2 text-right">Intentadas</th>
                        <th class="px-3 py-2 text-right">Gestionadas</th>
                        <th class="px-3 py-2 text-right">Efectividad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($ranking as $r)
                        @php
                            $ef = $r->cuentas_intentadas > 0 ? round(($r->cuentas_gestionadas / $r->cuentas_intentadas) * 100, 1) : 0.0;
                        @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $r->name }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($r->total_gestiones) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($r->cuentas_intentadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-emerald-700">{{ number_format($r->cuentas_gestionadas) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-blue-700">{{ number_format($ef, 1) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
            Gestiones recientes ({{ $gestiones->count() }})
        </div>
        @if($gestiones->isEmpty())
            <div class="p-6 text-sm text-gray-500 text-center">Sin gestiones registradas.</div>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Fecha</th>
                        <th class="px-3 py-2 text-left">Persona</th>
                        <th class="px-3 py-2 text-left">Tipo caso</th>
                        <th class="px-3 py-2 text-left">Resultado</th>
                        <th class="px-3 py-2 text-left">Canal</th>
                        <th class="px-3 py-2 text-left">Gestor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($gestiones as $g)
                        @php
                            $nombre = $g->tipo_persona === 'juridica'
                                ? (string) ($g->razon_social ?? '')
                                : trim((string) ($g->nombres ?? '').' '.(string) ($g->apellidos ?? ''));
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Carbon::parse($g->creada_en)->format('d/m H:i') }}</td>
                            <td class="px-3 py-2">
                                <div class="text-gray-900">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                <div class="text-[10px] text-gray-500 font-mono">{{ $g->identificacion }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ ucfirst(str_replace('_', ' ', (string) $g->tipo_caso)) }}</td>
                            <td class="px-3 py-2 text-xs">
                                {{ $g->resultado_nombre ?? '—' }}
                                @if($g->es_contacto_efectivo)
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] bg-emerald-100 text-emerald-800">efectivo</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $g->canal ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $g->usuario ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
