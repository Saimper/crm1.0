<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Application\DTOs\AsignacionMasivaResultado;
use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reparte entre los miembros de un equipo las cuentas del proyecto que no tienen
 * dueño, en round-robin. Se apoya en el único `(proyecto_id, caso_id)`: una
 * cuenta, un dueño.
 *
 * Entradas:
 *   - proyectoId: scope obligatorio.
 *   - equipoId:   equipo activo con al menos un miembro activo.
 *   - limite:     máximo de cuentas a repartir (0 = todas las elegibles).
 *
 * Reglas:
 *   - No se tocan asignaciones existentes (idempotente).
 *   - Solo se consideran casos del mismo proyecto y no cerrados.
 *   - Si el equipo no tiene miembros activos, falla.
 */
final readonly class AsignarCasosAEquipo
{
    public function __construct(
        private ConnectionInterface $db,
        private GeneradorNotificaciones $notificaciones,
    ) {}

    public function execute(
        int $proyectoId,
        int $equipoId,
        int $limite = 0,
    ): AsignacionMasivaResultado {
        $miembros = DB::table('equipo_usuario')
            ->where('proyecto_id', $proyectoId)
            ->where('equipo_id', $equipoId)
            ->where('activo', true)
            ->pluck('usuario_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($miembros === []) {
            throw new RuntimeException('El equipo no tiene miembros activos.');
        }

        $equipoValido = DB::table('equipos')
            ->where('id', $equipoId)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();
        if (! $equipoValido) {
            throw new RuntimeException('El equipo no existe o no está activo en el proyecto.');
        }

        $casosQ = DB::table('casos as c')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('asignaciones as a')
                ->whereColumn('a.caso_id', 'c.id')
                ->where('a.proyecto_id', $proyectoId))
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.cerrado_en')
            ->whereNull('c.eliminada_en')
            ->select(['c.id'])
            ->orderBy('c.id');

        if ($limite > 0) {
            $casosQ->limit($limite);
        }

        $casos = $casosQ->pluck('id')->map(fn ($v) => (int) $v)->all();

        if ($casos === []) {
            return new AsignacionMasivaResultado(asignadas: 0, omitidas: 0, distribucion: []);
        }

        $ahora = new DateTimeImmutable;
        $distribucion = array_fill_keys($miembros, 0);
        $asignadas = 0;
        $omitidas = 0;

        $this->db->transaction(function () use (
            &$asignadas, &$omitidas, &$distribucion,
            $casos, $miembros, $proyectoId, $ahora,
        ): void {
            $idx = 0;
            $total = count($miembros);
            foreach ($casos as $casoId) {
                $usuarioId = $miembros[$idx % $total];
                $idx++;

                $inserted = DB::table('asignaciones')->insertOrIgnore([
                    'public_id' => (string) Str::ulid(),
                    'proyecto_id' => $proyectoId,
                    'caso_id' => $casoId,
                    'usuario_id' => $usuarioId,
                    'fecha_asignacion' => $ahora->format('Y-m-d'),
                    'prioridad' => 100,
                    'estado' => 'pendiente',
                    'creada_en' => $ahora->format('Y-m-d H:i:s'),
                ]);

                if ($inserted === 1) {
                    $asignadas++;
                    $distribucion[$usuarioId]++;
                } else {
                    $omitidas++;
                }
            }
        });

        $distribucionFinal = array_filter($distribucion, fn ($v) => $v > 0);

        if ($distribucionFinal !== []) {
            $this->notificaciones->registrarAsignacionesRecibidas(
                proyectoId: $proyectoId,
                distribucion: $distribucionFinal,
                contexto: 'asignacion',
            );
        }

        return new AsignacionMasivaResultado(
            asignadas: $asignadas,
            omitidas: $omitidas,
            distribucion: $distribucionFinal,
        );
    }
}
