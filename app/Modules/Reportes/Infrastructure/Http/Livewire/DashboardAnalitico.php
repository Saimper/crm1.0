<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Dashboard analítico scoped al proyecto activo. Requiere `reportes.analiticos`.
 * Muestra: distribución por tipo_caso, compromisos por estado (total), evolución mensual
 * de gestiones (últimos 6 meses), efectividad por resultado, top-5 días con más gestiones.
 */
final class DashboardAnalitico extends Component
{
    public function render(): View
    {
        abort_unless(auth()->user()?->tienePermiso('reportes.analiticos') === true, 403);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;

        $distribucionCasos = DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->select('tipo_caso', DB::raw('count(*) as total'))
            ->groupBy('tipo_caso')
            ->orderByDesc('total')
            ->get();

        $compromisosPorEstado = DB::table('compromisos')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->select('estado', 'tipo_compromiso', DB::raw('count(*) as total'))
            ->groupBy('estado', 'tipo_compromiso')
            ->orderBy('tipo_compromiso')
            ->orderBy('estado')
            ->get();

        $desde = Carbon::now()->subMonths(5)->startOfMonth();
        $gestionesPorMes = DB::table('gestiones')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->where('creada_en', '>=', $desde)
            ->select(DB::raw("date_format(creada_en, '%Y-%m') as mes"), DB::raw('count(*) as total'))
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $efectividadPorResultado = DB::table('gestiones as g')
            ->join('resultados as r', 'r.id', '=', 'g.resultado_id')
            ->where('g.proyecto_id', $proyectoId)
            ->whereNull('g.eliminada_en')
            ->select([
                'r.codigo', 'r.nombre', 'r.es_contacto_efectivo',
                DB::raw('count(*) as total'),
            ])
            ->groupBy('r.id', 'r.codigo', 'r.nombre', 'r.es_contacto_efectivo')
            ->orderByDesc('total')
            ->get();

        $totalGestiones = (int) $efectividadPorResultado->sum('total');

        $topDias = DB::table('gestiones')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->select(DB::raw('date(creada_en) as dia'), DB::raw('count(*) as total'))
            ->groupBy('dia')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('reportes::livewire.dashboard-analitico', [
            'proyecto'              => $proyecto,
            'distribucionCasos'     => $distribucionCasos,
            'compromisosPorEstado'  => $compromisosPorEstado,
            'gestionesPorMes'       => $gestionesPorMes,
            'efectividadPorResultado' => $efectividadPorResultado,
            'totalGestiones'        => $totalGestiones,
            'topDias'               => $topDias,
        ]);
    }
}
