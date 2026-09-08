<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use Illuminate\Database\ConnectionInterface;

/**
 * El supervisor le pasa UNA cuenta de un asesor a otro.
 *
 * Es el hermano fino de `ReasignarCasosEntreEquipos`, que mueve lotes enteros
 * round-robin: aquí se trata del caso concreto —el asesor se fue de vacaciones,
 * la cuenta le quedó grande, el cliente pidió otro interlocutor—.
 *
 * Se reasigna cambiando el dueño de la fila, no cerrando y creando otra: el
 * único `(proyecto_id, caso_id)` lo prohíbe, y además la cuenta es la misma; lo
 * que cambia es quién responde por ella.
 *
 * Se permite mover una asignación `en_trabajo` —a diferencia del movimiento por
 * lotes, que respeta lo empezado—: aquí el supervisor está mirando esa fila y
 * decidiendo sobre ella. Una asignación `cerrada` no se toca: ya es historia.
 */
final readonly class ReasignarAsignacionAUsuario
{
    private const ESTADOS_REASIGNABLES = ['pendiente', 'en_trabajo'];

    public function __construct(
        private ConnectionInterface $db,
        private GeneradorNotificaciones $notificaciones,
    ) {}

    public function execute(int $proyectoId, int $asignacionId, int $nuevoUsuarioId): void
    {
        $asignacion = $this->db->table('asignaciones')
            ->where('id', $asignacionId)
            ->where('proyecto_id', $proyectoId)
            ->first();

        if ($asignacion === null) {
            throw new TransicionAsignacionInvalida('La asignación no existe en este proyecto.');
        }

        if (! in_array($asignacion->estado, self::ESTADOS_REASIGNABLES, true)) {
            throw new TransicionAsignacionInvalida('Una asignación cerrada ya no se reasigna.');
        }

        if ((int) $asignacion->usuario_id === $nuevoUsuarioId) {
            throw new TransicionAsignacionInvalida('La cuenta ya es de ese asesor.');
        }

        if (! $this->usuarioOperaEnProyecto($nuevoUsuarioId, $proyectoId)) {
            throw new TransicionAsignacionInvalida('El asesor destino no tiene acceso a este proyecto.');
        }

        $this->db->transaction(function () use ($asignacionId, $proyectoId, $nuevoUsuarioId): void {
            $this->db->table('asignaciones')
                ->where('id', $asignacionId)
                ->where('proyecto_id', $proyectoId)
                ->whereIn('estado', self::ESTADOS_REASIGNABLES)
                ->update(['usuario_id' => $nuevoUsuarioId]);
        });

        $this->notificaciones->registrarAsignacionesRecibidas(
            proyectoId: $proyectoId,
            distribucion: [$nuevoUsuarioId => 1],
            contexto: 'reasignacion',
        );
    }

    /**
     * Vale cualquiera de las dos puertas de entrada al proyecto: el rol base o
     * un rol custom (F33). No se exige permiso operativo concreto —eso lo
     * decide el rol— sino que el asesor pertenezca al proyecto: darle una
     * cuenta a alguien que no puede abrirla es perderla.
     */
    private function usuarioOperaEnProyecto(int $usuarioId, int $proyectoId): bool
    {
        $enRolBase = $this->db->table('usuario_proyecto_rol')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();

        if ($enRolBase) {
            return true;
        }

        return $this->db->table('usuario_proyecto_rol_custom')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();
    }
}
