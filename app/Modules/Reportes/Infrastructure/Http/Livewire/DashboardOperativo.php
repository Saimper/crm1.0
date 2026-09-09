<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard operativo scoped al proyecto activo. Solo usuarios con `reportes.operativos`.
 * Métricas comunes (§10.1 CLAUDE.md): cuentas intentadas, gestionadas, totales, compromisos vigentes/vencidos.
 *
 * El rango lo calcula `RelojDelMandante::rangoPreestablecido`, el mismo que
 * usa la exportación de gestiones: lo que se ve aquí es lo que se descarga.
 */
final class DashboardOperativo extends Component
{
    /** @var array<string, string> Clave de rango → clave de idioma de su etiqueta. */
    private const ETIQUETAS = [
        'hoy' => 'reportes.range_today',
        'ayer' => 'reportes.range_yesterday',
        'semana' => 'reportes.range_last7',
        'mes' => 'reportes.range_current_month',
    ];

    /** Viene de la URL: puede traer cualquier cosa, no sólo las cuatro claves. */
    #[Url(as: 'rango')]
    public string $rango = 'hoy';

    public function render(): View
    {
        abort_unless(auth()->user()?->tienePermiso('reportes.operativos') === true, 403);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;

        // Cualquier valor fuera del selector cuenta como «hoy», igual que en el
        // reloj: así la etiqueta y el enlace de exportar dicen lo mismo que la consulta.
        $rangoClave = isset(self::ETIQUETAS[$this->rango]) ? $this->rango : 'hoy';

        // En la zona del cliente, no en la del servidor: cortar el día en UTC
        // metía el último tramo del turno de tarde en el día siguiente.
        $reloj = app(RelojDelMandante::class);
        $rango = $reloj->rangoPreestablecido($rangoClave);
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

        $cuentasIntentadas = (clone $gestionesBase)->distinct()->count('caso_id');
        $cuentasGestionadas = $resultadosEfectivos === []
            ? 0
            : (clone $gestionesBase)->whereIn('resultado_id', $resultadosEfectivos)->distinct()->count('caso_id');
        $totalGestiones = (clone $gestionesBase)->count();
        $efectividad = $cuentasIntentadas === 0 ? 0.0 : round(($cuentasGestionadas / $cuentasIntentadas) * 100, 1);

        $hoy = $reloj->hoy();
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
            ->leftJoin('casos as ca', 'ca.id', '=', 'g.caso_id')
            ->leftJoin('personas as pe', 'pe.id', '=', 'g.persona_id')
            ->leftJoin('resultados as r', 'r.id', '=', 'g.resultado_id')
            ->leftJoin('tipos_gestion as tg', 'tg.id', '=', 'g.tipo_gestion_id')
            ->leftJoin('canales as cn', 'cn.id', '=', 'g.canal_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.usuario_id')
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
            'proyecto' => $proyecto,
            'etiquetaRango' => __(self::ETIQUETAS[$rangoClave]),
            'cuentasIntentadas' => $cuentasIntentadas,
            'cuentasGestionadas' => $cuentasGestionadas,
            'totalGestiones' => $totalGestiones,
            'efectividad' => $efectividad,
            'compromisosVigentes' => $compromisosVigentes,
            'compromisosVencidos' => $compromisosVencidos,
            'ranking' => $ranking,
            'gestiones' => $gestionesDelRango,
            'urlExportarGestiones' => route('proyectos.gestiones.exportar', ['proyecto_id' => $proyectoId, 'rango' => $rangoClave]),
            'urlExportarPorFechas' => route('proyectos.gestiones.exportar', ['proyecto_id' => $proyectoId]),
            'hoyDelCliente' => $hoy,
        ]);
    }
}
