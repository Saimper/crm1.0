<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Support\Database\CarterasOperativas;
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
        $usuario = auth()->user();
        abort_unless($usuario !== null && $usuario->tienePermiso('reportes.operativos'), 403);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;
        $carteras = $usuario->carterasPermitidasParaPermiso('reportes.operativos', $proyectoId);

        $rango = $this->rangoActual();
        $desde = $rango['desde'];
        $hasta = $rango['hasta'];
        $hoy = app(RelojDelMandante::class)->hoy();

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

            $gestionesQ = CarterasOperativas::filtrarVinculados(DB::table('gestiones'), 'gestiones', carterasPermitidas: $carteras)
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

            $compromisosVigentes = CarterasOperativas::filtrarVinculados(DB::table('compromisos'), 'compromisos', carterasPermitidas: $carteras)
                ->where('proyecto_id', $proyectoId)
                ->where('estado', 'pendiente')
                ->whereIn('usuario_id', $miembroIds)
                ->whereDate('fecha_vencimiento', '>=', $hoy)
                ->whereNull('eliminada_en')
                ->count();
            $compromisosVencidos = CarterasOperativas::filtrarVinculados(DB::table('compromisos'), 'compromisos', carterasPermitidas: $carteras)
                ->where('proyecto_id', $proyectoId)
                ->where('estado', 'pendiente')
                ->whereIn('usuario_id', $miembroIds)
                ->whereDate('fecha_vencimiento', '<', $hoy)
                ->whereNull('eliminada_en')
                ->count();

            $filas[] = [
                'equipo' => $eq,
                'miembros_count' => count($miembroIds),
                'total_gestiones' => $totalGestiones,
                'cuentas_intentadas' => $intentadas,
                'cuentas_gestionadas' => $gestionadas,
                'efectividad' => $efectividad,
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
                $carteras,
            );
        }

        return view('reportes::livewire.reporte-equipos', [
            'proyecto' => $proyecto,
            'etiquetaRango' => $rango['etiqueta'],
            'filas' => $filas,
            'detalle' => $detalle,
        ]);
    }

    /**
     * @param  array<int, object>  $_
     * @param  list<int>  $resultadosEfectivos
     * @param  list<int>|null  $carteras
     * @return list<array<string, mixed>>
     */
    private function breakdownPorMiembro(
        int $proyectoId,
        int $equipoId,
        Carbon $desde,
        Carbon $hasta,
        array $resultadosEfectivos,
        ?array $carteras,
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
            $q = CarterasOperativas::filtrarVinculados(DB::table('gestiones'), 'gestiones', carterasPermitidas: $carteras)
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
                'usuario_id' => (int) $u->id,
                'nombre' => (string) $u->name,
                'email' => (string) $u->email,
                'total' => $total,
                'intentadas' => $intentadas,
                'gestionadas' => $gestionadas,
                'efectividad' => $efectividad,
            ];
        }

        return $res;
    }

    /** @return array<string, mixed> */
    private function filaVacia(object $eq): array
    {
        return [
            'equipo' => $eq,
            'miembros_count' => 0,
            'total_gestiones' => 0,
            'cuentas_intentadas' => 0,
            'cuentas_gestionadas' => 0,
            'efectividad' => 0.0,
            'compromisos_vigentes' => 0,
            'compromisos_vencidos' => 0,
        ];
    }

    /** @return array{desde: Carbon, hasta: Carbon, etiqueta: string} */
    private function rangoActual(): array
    {
        $range = app(RelojDelMandante::class)->rangoPreestablecido($this->rango);

        return $range + ['etiqueta' => match ($this->rango) {
            'hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Últimos 7 días', default => 'Mes en curso',
        }];
    }
}
