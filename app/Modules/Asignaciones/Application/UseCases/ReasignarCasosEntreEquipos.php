<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Application\DTOs\AsignacionMasivaResultado;
use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mueve asignaciones en estado `pendiente` del equipo origen al equipo destino,
 * distribuyendo round-robin entre los miembros activos del destino.
 *
 * Reglas:
 *   - Solo se mueven asignaciones `estado = pendiente`. Las `en_trabajo` o `cerrada`
 *     se respetan (el gestor ya tocó el caso o ya cerró).
 *   - Ambos equipos deben pertenecer al proyecto, estar activos y el destino debe tener
 *     al menos un miembro activo.
 *   - Origen y destino distintos.
 *   - Update atómico por transacción; idempotencia natural: segunda corrida no encuentra
 *     pendientes con usuario ∈ origen porque ya se movieron.
 */
final readonly class ReasignarCasosEntreEquipos
{
    public function __construct(
        private ConnectionInterface $db,
        private GeneradorNotificaciones $notificaciones,
    ) {}

    public function execute(
        int $proyectoId,
        int $equipoOrigenId,
        int $equipoDestinoId,
        int $limite = 0,
    ): AsignacionMasivaResultado {
        if ($equipoOrigenId === $equipoDestinoId) {
            throw new RuntimeException('El equipo origen y destino deben ser distintos.');
        }

        $origenValido = DB::table('equipos')
            ->where('id', $equipoOrigenId)
            ->where('proyecto_id', $proyectoId)
            ->exists();
        if (! $origenValido) {
            throw new RuntimeException('Equipo origen no pertenece al proyecto activo.');
        }

        $destinoValido = DB::table('equipos')
            ->where('id', $equipoDestinoId)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();
        if (! $destinoValido) {
            throw new RuntimeException('Equipo destino no existe o no está activo.');
        }

        $miembrosOrigen = DB::table('equipo_usuario')
            ->where('proyecto_id', $proyectoId)
            ->where('equipo_id', $equipoOrigenId)
            ->where('activo', true)
            ->pluck('usuario_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($miembrosOrigen === []) {
            return new AsignacionMasivaResultado(asignadas: 0, omitidas: 0, distribucion: []);
        }

        $miembrosDestino = DB::table('equipo_usuario')
            ->where('proyecto_id', $proyectoId)
            ->where('equipo_id', $equipoDestinoId)
            ->where('activo', true)
            ->pluck('usuario_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($miembrosDestino === []) {
            throw new RuntimeException('El equipo destino no tiene miembros activos.');
        }

        $pendientesQ = DB::table('asignaciones')
            ->where('proyecto_id', $proyectoId)
            ->where('estado', 'pendiente')
            ->whereIn('usuario_id', $miembrosOrigen)
            ->orderBy('id')
            ->select('id');

        if ($limite > 0) {
            $pendientesQ->limit($limite);
        }

        $asignacionIds = $pendientesQ->pluck('id')->map(fn ($v) => (int) $v)->all();

        if ($asignacionIds === []) {
            return new AsignacionMasivaResultado(asignadas: 0, omitidas: 0, distribucion: []);
        }

        $distribucion = array_fill_keys($miembrosDestino, 0);
        $movidas = 0;

        $this->db->transaction(function () use (
            &$movidas, &$distribucion,
            $asignacionIds, $miembrosDestino, $proyectoId,
        ): void {
            $idx = 0;
            $total = count($miembrosDestino);
            foreach ($asignacionIds as $asignacionId) {
                $nuevoUsuarioId = $miembrosDestino[$idx % $total];
                $idx++;

                DB::table('asignaciones')
                    ->where('id', $asignacionId)
                    ->where('proyecto_id', $proyectoId)
                    ->where('estado', 'pendiente')
                    ->update(['usuario_id' => $nuevoUsuarioId]);

                $distribucion[$nuevoUsuarioId]++;
                $movidas++;
            }
        });

        $distribucionFinal = array_filter($distribucion, fn ($v) => $v > 0);

        if ($distribucionFinal !== []) {
            $this->notificaciones->registrarAsignacionesRecibidas(
                proyectoId: $proyectoId,
                distribucion: $distribucionFinal,
                contexto: 'reasignacion',
            );
        }

        return new AsignacionMasivaResultado(
            asignadas: $movidas,
            omitidas: 0,
            distribucion: $distribucionFinal,
        );
    }
}
