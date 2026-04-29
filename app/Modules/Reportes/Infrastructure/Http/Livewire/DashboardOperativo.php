<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard operativo scoped al proyecto activo. Solo usuarios con `reportes.operativos`.
 * Métricas comunes (§10.1 CLAUDE.md): cuentas intentadas, gestionadas, totales, compromisos vigentes/vencidos.
 */
final class DashboardOperativo extends Component
{
    /** @var 'hoy'|'ayer'|'semana'|'mes' */
    #[Url(as: 'rango')]
    public string $rango = 'hoy';

    public function render(): View
    {
        abort_unless(auth()->user()?->tienePermiso('reportes.operativos') === true, 403);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;

        $rango = $this->rangoActual();
        $desde = $rango['desde'];
        $hasta = $rango['hasta'];

        // IDs de resultados con bandera es_contacto_efectivo del proyecto activo.
        $resultadosEfectivos = DB::table('resultados')
            ->where('proyecto_id', $proyectoId)
            ->where('es_contacto_efectivo', true)
            ->pluck('id')
            ->all();

        $gestionesBase = DB::table('gestiones')
            ->where('proyecto_id', $proyectoId)
            ->whereBetween('creada_en', [$desde, $hasta])
            ->whereNull('eliminada_en');

        $cuentasIntentadas  = (clone $gestionesBase)->distinct()->count('caso_id');
        $cuentasGestionadas = $resultadosEfectivos === []
            ? 0
            : (clone $gestionesBase)->whereIn('resultado_id', $resultadosEfectivos)->distinct()->count('caso_id');
        $totalGestiones = (clone $gestionesBase)->count();
        $efectividad    = $cuentasIntentadas === 0 ? 0.0 : round(($cuentasGestionadas / $cuentasIntentadas) * 100, 1);

        $hoy = Carbon::today();
        $compromisosVigentes = DB::table('compromisos')
            ->where('proyecto_id', $proyectoId)
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->whereNull('eliminada_en')
            ->count();
        $compromisosVencidos = DB::table('compromisos')
            ->where('proyecto_id', $proyectoId)
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->whereNull('eliminada_en')
            ->count();

        $rankingSelect = [
            'u.id',
            'u.name',
            DB::raw('count(*) as total_gestiones'),
            DB::raw('count(distinct g.caso_id) as cuentas_intentadas'),
        ];
        if ($resultadosEfectivos !== []) {
            $in = implode(',', array_map('intval', $resultadosEfectivos));
            $rankingSelect[] = DB::raw("count(distinct case when g.resultado_id in ({$in}) then g.caso_id end) as cuentas_gestionadas");
        } else {
            $rankingSelect[] = DB::raw('0 as cuentas_gestionadas');
        }

        $ranking = DB::table('gestiones as g')
            ->join('users as u', 'u.id', '=', 'g.usuario_id')
            ->where('g.proyecto_id', $proyectoId)
            ->whereBetween('g.creada_en', [$desde, $hasta])
            ->whereNull('g.eliminada_en')
            ->select($rankingSelect)
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_gestiones')
            ->limit(10)
            ->get();

        $gestionesDelRango = DB::table('gestiones as g')
            ->leftJoin('casos as ca',         'ca.id',  '=', 'g.caso_id')
            ->leftJoin('personas as pe',      'pe.id',  '=', 'g.persona_id')
            ->leftJoin('resultados as r',     'r.id',   '=', 'g.resultado_id')
            ->leftJoin('tipos_gestion as tg', 'tg.id',  '=', 'g.tipo_gestion_id')
            ->leftJoin('canales as cn',       'cn.id',  '=', 'g.canal_id')
            ->leftJoin('users as u',          'u.id',   '=', 'g.usuario_id')
            ->where('g.proyecto_id', $proyectoId)
            ->whereBetween('g.creada_en', [$desde, $hasta])
            ->whereNull('g.eliminada_en')
            ->select([
                'g.id', 'g.creada_en',
                'ca.public_id as caso_public_id', 'ca.tipo_caso',
                'pe.public_id as persona_public_id', 'pe.identificacion',
                'pe.nombres', 'pe.apellidos', 'pe.razon_social', 'pe.tipo_persona',
                'r.nombre as resultado_nombre', 'r.es_contacto_efectivo',
                'tg.nombre as tipo_gestion',
                'cn.nombre as canal',
                'u.name as usuario',
            ])
            ->orderByDesc('g.creada_en')
            ->limit(50)
            ->get();

        return view('reportes::livewire.dashboard-operativo', [
            'proyecto'            => $proyecto,
            'etiquetaRango'       => $rango['etiqueta'],
            'cuentasIntentadas'   => $cuentasIntentadas,
            'cuentasGestionadas'  => $cuentasGestionadas,
            'totalGestiones'      => $totalGestiones,
            'efectividad'         => $efectividad,
            'compromisosVigentes' => $compromisosVigentes,
            'compromisosVencidos' => $compromisosVencidos,
            'ranking'             => $ranking,
            'gestiones'           => $gestionesDelRango,
        ]);
    }

    /** @return array{desde: Carbon, hasta: Carbon, etiqueta: string} */
    private function rangoActual(): array
    {
        $ahora = Carbon::now();

        return match ($this->rango) {
            'ayer'   => [
                'desde'    => $ahora->copy()->subDay()->startOfDay(),
                'hasta'    => $ahora->copy()->subDay()->endOfDay(),
                'etiqueta' => 'Ayer',
            ],
            'semana' => [
                'desde'    => $ahora->copy()->subDays(6)->startOfDay(),
                'hasta'    => $ahora->copy()->endOfDay(),
                'etiqueta' => 'Últimos 7 días',
            ],
            'mes'    => [
                'desde'    => $ahora->copy()->startOfMonth(),
                'hasta'    => $ahora->copy()->endOfDay(),
                'etiqueta' => 'Mes en curso',
            ],
            default  => [
                'desde'    => $ahora->copy()->startOfDay(),
                'hasta'    => $ahora->copy()->endOfDay(),
                'etiqueta' => 'Hoy',
            ],
        };
    }
}
