<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reporte por equipos del proyecto activo. Agrega métricas operativas por equipo
 * sumando sobre sus miembros (equipo_usuario). Permiso: reportes.operativos.
 */
final class ReporteEquipos extends Component
{
    /** @var 'hoy'|'ayer'|'semana'|'mes' */
    #[Url(as: 'rango')]
    public string $rango = 'mes';

    public ?int $equipoExpandidoId = null;

    public function expandir(int $equipoId): void
    {
        $this->equipoExpandidoId = $this->equipoExpandidoId === $equipoId ? null : $equipoId;
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->tienePermiso('reportes.operativos') === true, 403);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;

        $rango = $this->rangoActual();
        $desde = $rango['desde'];
        $hasta = $rango['hasta'];
        $hoy = Carbon::today();

        $resultadosEfectivos = DB::table('resultados')
            ->where('proyecto_id', $proyectoId)
            ->where('es_contacto_efectivo', true)
            ->pluck('id')
            ->all();
        $inEfectivos = $resultadosEfectivos === []
            ? null
            : implode(',', array_map('intval', $resultadosEfectivos));

        $equipos = DB::table('equipos')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $filas = [];
        foreach ($equipos as $eq) {
            $miembroIds = DB::table('equipo_usuario')
                ->where('proyecto_id', $proyectoId)
                ->where('equipo_id', $eq->id)
                ->where('activo', true)
                ->pluck('usuario_id')
                ->all();

            if ($miembroIds === []) {
                $filas[] = $this->filaVacia($eq);
                continue;
            }

            $gestionesQ = DB::table('gestiones')
                ->where('proyecto_id', $proyectoId)
                ->whereBetween('creada_en', [$desde, $hasta])
                ->whereIn('usuario_id', $miembroIds)
                ->whereNull('eliminada_en');

            $totalGestiones = (clone $gestionesQ)->count();
            $intentadas = (clone $gestionesQ)->distinct()->count('caso_id');
            $gestionadas = $inEfectivos === null
                ? 0
                : (clone $gestionesQ)->whereIn('resultado_id', $resultadosEfectivos)->distinct()->count('caso_id');
            $efectividad = $intentadas === 0 ? 0.0 : round(($gestionadas / $intentadas) * 100, 1);

            $compromisosVigentes = DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)
                ->where('estado', 'pendiente')
                ->whereIn('usuario_id', $miembroIds)
                ->whereDate('fecha_vencimiento', '>=', $hoy)
                ->whereNull('eliminada_en')
                ->count();
            $compromisosVencidos = DB::table('compromisos')
                ->where('proyecto_id', $proyectoId)
                ->where('estado', 'pendiente')
                ->whereIn('usuario_id', $miembroIds)
                ->whereDate('fecha_vencimiento', '<', $hoy)
                ->whereNull('eliminada_en')
                ->count();

            $filas[] = [
                'equipo'               => $eq,
                'miembros_count'       => count($miembroIds),
                'total_gestiones'      => $totalGestiones,
                'cuentas_intentadas'   => $intentadas,
                'cuentas_gestionadas'  => $gestionadas,
                'efectividad'          => $efectividad,
                'compromisos_vigentes' => $compromisosVigentes,
                'compromisos_vencidos' => $compromisosVencidos,
            ];
        }

        $detalle = null;
        if ($this->equipoExpandidoId !== null) {
            $detalle = $this->breakdownPorMiembro(
                $proyectoId,
                $this->equipoExpandidoId,
                $desde,
                $hasta,
                $resultadosEfectivos,
            );
        }

        return view('reportes::livewire.reporte-equipos', [
            'proyecto'      => $proyecto,
            'etiquetaRango' => $rango['etiqueta'],
            'filas'         => $filas,
            'detalle'       => $detalle,
        ]);
    }

    /**
     * @param array<int, object> $_
     * @param list<int> $resultadosEfectivos
     * @return list<array<string, mixed>>
     */
    private function breakdownPorMiembro(
        int $proyectoId,
        int $equipoId,
        Carbon $desde,
        Carbon $hasta,
        array $resultadosEfectivos,
    ): array {
        $rows = DB::table('equipo_usuario as eu')
            ->join('users as u', 'u.id', '=', 'eu.usuario_id')
            ->where('eu.proyecto_id', $proyectoId)
            ->where('eu.equipo_id', $equipoId)
            ->where('eu.activo', true)
            ->select(['u.id', 'u.name', 'u.email'])
            ->orderBy('u.name')
            ->get();

        $inEfectivos = $resultadosEfectivos === []
            ? null
            : implode(',', array_map('intval', $resultadosEfectivos));

        $res = [];
        foreach ($rows as $u) {
            $q = DB::table('gestiones')
                ->where('proyecto_id', $proyectoId)
                ->where('usuario_id', $u->id)
                ->whereBetween('creada_en', [$desde, $hasta])
                ->whereNull('eliminada_en');

            $total = (clone $q)->count();
            $intentadas = (clone $q)->distinct()->count('caso_id');
            $gestionadas = $inEfectivos === null
                ? 0
                : (clone $q)->whereIn('resultado_id', $resultadosEfectivos)->distinct()->count('caso_id');
            $efectividad = $intentadas === 0 ? 0.0 : round(($gestionadas / $intentadas) * 100, 1);

            $res[] = [
                'usuario_id'   => (int) $u->id,
                'nombre'       => (string) $u->name,
                'email'        => (string) $u->email,
                'total'        => $total,
                'intentadas'   => $intentadas,
                'gestionadas'  => $gestionadas,
                'efectividad'  => $efectividad,
            ];
        }

        return $res;
    }

    /** @return array<string, mixed> */
    private function filaVacia(object $eq): array
    {
        return [
            'equipo'               => $eq,
            'miembros_count'       => 0,
            'total_gestiones'      => 0,
            'cuentas_intentadas'   => 0,
            'cuentas_gestionadas'  => 0,
            'efectividad'          => 0.0,
            'compromisos_vigentes' => 0,
            'compromisos_vencidos' => 0,
        ];
    }

    /** @return array{desde: Carbon, hasta: Carbon, etiqueta: string} */
    private function rangoActual(): array
    {
        $ahora = Carbon::now();

        return match ($this->rango) {
            'hoy' => [
                'desde'    => $ahora->copy()->startOfDay(),
                'hasta'    => $ahora->copy()->endOfDay(),
                'etiqueta' => 'Hoy',
            ],
            'ayer' => [
                'desde'    => $ahora->copy()->subDay()->startOfDay(),
                'hasta'    => $ahora->copy()->subDay()->endOfDay(),
                'etiqueta' => 'Ayer',
            ],
            'semana' => [
                'desde'    => $ahora->copy()->subDays(6)->startOfDay(),
                'hasta'    => $ahora->copy()->endOfDay(),
                'etiqueta' => 'Últimos 7 días',
            ],
            default => [
                'desde'    => $ahora->copy()->startOfMonth(),
                'hasta'    => $ahora->copy()->endOfDay(),
                'etiqueta' => 'Mes en curso',
            ],
        };
    }
}
