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
 * Asigna casos sin asignación activa en una campaña a los miembros de un equipo
 * usando distribución round-robin. Respeta el unique (campana_id, caso_id) de la tabla.
 *
 * Entradas:
 *   - proyectoId: scope obligatorio.
 *   - campanaId:  la campaña a la que pertenecerán las nuevas asignaciones.
 *   - equipoId:   equipo activo con al menos un miembro activo.
 *   - limite:     máximo de casos a asignar (0 = todos los elegibles).
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
        int $campanaId,
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

        $campanaValida = DB::table('campanas')
            ->where('id', $campanaId)
            ->where('proyecto_id', $proyectoId)
            ->exists();
        if (! $campanaValida) {
            throw new RuntimeException('La campaña no pertenece al proyecto activo.');
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
            ->leftJoin('asignaciones as a', function ($join) use ($campanaId) {
                $join->on('a.caso_id', '=', 'c.id')
                    ->where('a.campana_id', '=', $campanaId);
            })
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.cerrado_en')
            ->whereNull('c.eliminada_en')
            ->whereNull('a.id')
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
            $casos, $miembros, $proyectoId, $campanaId, $ahora,
        ): void {
            $idx = 0;
            $total = count($miembros);
            foreach ($casos as $casoId) {
                $usuarioId = $miembros[$idx % $total];
                $idx++;

                $inserted = DB::table('asignaciones')->insertOrIgnore([
                    'public_id' => (string) Str::ulid(),
                    'proyecto_id' => $proyectoId,
                    'campana_id' => $campanaId,
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
