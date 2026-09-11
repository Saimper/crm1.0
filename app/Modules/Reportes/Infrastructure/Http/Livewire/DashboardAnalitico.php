<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Support\Database\CarterasOperativas;
use App\Support\Database\LocalCalendarSql;
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
        $usuario = auth()->user();
        abort_unless($usuario !== null && $usuario->tienePermiso('reportes.analiticos'), 403);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;
        $carteras = $usuario->carterasPermitidasParaPermiso('reportes.analiticos', $proyectoId);

        $distribucionCasos = CarterasOperativas::casos(DB::connection(), $proyectoId, $carteras)
            ->select('tipo_caso', DB::raw('count(*) as total'))
            ->groupBy('tipo_caso')
            ->orderByDesc('total')
            ->get();

        $compromisosPorEstado = CarterasOperativas::filtrarVinculados(DB::table('compromisos'), 'compromisos', carterasPermitidas: $carteras)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->select('estado', 'tipo_compromiso', DB::raw('count(*) as total'))
            ->groupBy('estado', 'tipo_compromiso')
            ->orderBy('tipo_compromiso')
            ->orderBy('estado')
            ->get();

        $timezone = app(RegionalConfiguration::class)->forProject($proyectoId)->timezone;
        $day = LocalCalendarSql::expression('creada_en', $timezone);
        $month = LocalCalendarSql::expression('creada_en', $timezone, '%Y-%m');
        $desde = Carbon::now($timezone)->subMonths(5)->startOfMonth()->setTimezone('UTC');
        $gestionesPorMes = CarterasOperativas::filtrarVinculados(DB::table('gestiones'), 'gestiones', carterasPermitidas: $carteras)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->where('creada_en', '>=', $desde)
            ->select(DB::raw($month.' as mes'), DB::raw('count(*) as total'))
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $efectividadPorResultado = CarterasOperativas::filtrarVinculados(DB::table('gestiones as g'), 'g', carterasPermitidas: $carteras)
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

        $topDias = CarterasOperativas::filtrarVinculados(DB::table('gestiones'), 'gestiones', carterasPermitidas: $carteras)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->select(DB::raw($day.' as dia'), DB::raw('count(*) as total'))
            ->groupBy('dia')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('reportes::livewire.dashboard-analitico', [
            'proyecto' => $proyecto,
            'distribucionCasos' => $distribucionCasos,
            'compromisosPorEstado' => $compromisosPorEstado,
            'gestionesPorMes' => $gestionesPorMes,
            'efectividadPorResultado' => $efectividadPorResultado,
            'totalGestiones' => $totalGestiones,
            'topDias' => $topDias,
        ]);
    }
}
