<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Application\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Genera notificaciones operativas sin duplicar (unique por proyecto + destinatario + tipo + entidad).
 * - Compromisos pendientes que vencen en ≤ umbralDias → tipo=compromiso_por_vencer
 * - Compromisos pendientes ya vencidos              → tipo=compromiso_vencido
 * - Compromisos CX (resolución de ticket) cuyo fecha_limite_sla esté dentro de umbralHorasSla → tipo=sla_en_riesgo
 *
 * No toca notificaciones existentes ni estados de compromisos.
 */
final readonly class GeneradorNotificaciones
{
    public function ejecutar(int $umbralDias = 3, int $umbralHorasSla = 8, ?Carbon $ahora = null): int
    {
        $ahora ??= Carbon::now();
        $hoy = $ahora->toDateString();
        $limite = $ahora->copy()->addDays($umbralDias)->toDateString();

        $total = 0;

        $porVencer = DB::table('compromisos as c')
            ->select([
                'c.id', 'c.public_id', 'c.proyecto_id', 'c.usuario_id',
                'c.tipo_compromiso', 'c.fecha_vencimiento', 'c.caso_id',
            ])
            ->where('c.estado', 'pendiente')
            ->whereNull('c.eliminada_en')
            ->where('c.fecha_vencimiento', '>=', $hoy)
            ->where('c.fecha_vencimiento', '<=', $limite)
            ->get();

        foreach ($porVencer as $c) {
            $total += $this->insertar(
                proyectoId: (int) $c->proyecto_id,
                usuarioId: (int) $c->usuario_id,
                tipo: 'compromiso_por_vencer',
                entidadId: (int) $c->id,
                titulo: 'Compromiso próximo a vencer',
                mensaje: sprintf(
                    'El compromiso (%s) vence el %s.',
                    (string) $c->tipo_compromiso,
                    (string) $c->fecha_vencimiento,
                ),
                metadata: [
                    'caso_id' => (int) $c->caso_id,
                    'tipo_compromiso' => (string) $c->tipo_compromiso,
                    'fecha_vencimiento' => (string) $c->fecha_vencimiento,
                ],
            );
        }

        $vencidos = DB::table('compromisos as c')
            ->select([
                'c.id', 'c.proyecto_id', 'c.usuario_id',
                'c.tipo_compromiso', 'c.fecha_vencimiento', 'c.caso_id',
            ])
            ->where('c.estado', 'pendiente')
            ->whereNull('c.eliminada_en')
            ->where('c.fecha_vencimiento', '<', $hoy)
            ->get();

        foreach ($vencidos as $c) {
            $total += $this->insertar(
                proyectoId: (int) $c->proyecto_id,
                usuarioId: (int) $c->usuario_id,
                tipo: 'compromiso_vencido',
                entidadId: (int) $c->id,
                titulo: 'Compromiso vencido sin resolver',
                mensaje: sprintf(
                    'El compromiso (%s) venció el %s y sigue pendiente.',
                    (string) $c->tipo_compromiso,
                    (string) $c->fecha_vencimiento,
                ),
                metadata: [
                    'caso_id' => (int) $c->caso_id,
                    'tipo_compromiso' => (string) $c->tipo_compromiso,
                    'fecha_vencimiento' => (string) $c->fecha_vencimiento,
                ],
            );
        }

        $total += $this->generarSlaEnRiesgo($ahora, $umbralHorasSla);

        return $total;
    }

    /**
     * Registra una notificación por usuario para un batch de asignaciones recibidas.
     * Usa timestamp como entidad_id para garantizar unicidad entre ejecuciones distintas
     * sin duplicar dentro del mismo milisegundo (nunca ocurre en práctica).
     *
     * @param  array<int, int>  $distribucion  usuarioId => cantidad de asignaciones recibidas
     * @return int número de notificaciones efectivamente creadas (insertOrIgnore)
     */
    public function registrarAsignacionesRecibidas(
        int $proyectoId,
        array $distribucion,
        string $contexto = 'asignacion',
    ): int {
        if ($distribucion === []) {
            return 0;
        }

        $ahora = Carbon::now();
        $entidadId = (int) $ahora->getTimestamp();

        $creadas = 0;
        foreach ($distribucion as $usuarioId => $cantidad) {
            if ($cantidad <= 0) {
                continue;
            }

            $titulo = $contexto === 'reasignacion'
                ? 'Asignaciones transferidas a tu bandeja'
                : 'Nuevas asignaciones en tu bandeja';

            $creadas += (int) DB::table('notificaciones')->insertOrIgnore([
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'destinatario_usuario_id' => (int) $usuarioId,
                'tipo' => 'asignacion_recibida',
                'entidad_tipo' => 'asignaciones_batch',
                'entidad_id' => $entidadId + (int) $usuarioId,
                'titulo' => $titulo,
                'mensaje' => "Recibiste {$cantidad} caso(s).",
                'metadata' => json_encode([
                    'cantidad' => (int) $cantidad,
                    'contexto' => $contexto,
                ], JSON_UNESCAPED_UNICODE),
                'creada_en' => $ahora,
            ]);
        }

        return $creadas;
    }

    private function generarSlaEnRiesgo(Carbon $ahora, int $umbralHoras): int
    {
        $limiteInferior = $ahora->toDateTimeString();
        $limiteSuperior = $ahora->copy()->addHours($umbralHoras)->toDateTimeString();

        $enRiesgo = DB::table('compromisos as c')
            ->join('compromisos_resolucion_ticket as rt', 'rt.compromiso_id', '=', 'c.id')
            ->select([
                'c.id', 'c.proyecto_id', 'c.usuario_id', 'c.caso_id',
                'rt.fecha_limite_sla', 'rt.accion_comprometida',
            ])
            ->where('c.estado', 'pendiente')
            ->whereNull('c.eliminada_en')
            ->where('c.tipo_compromiso', 'resolucion_ticket')
            ->where('rt.fecha_limite_sla', '>=', $limiteInferior)
            ->where('rt.fecha_limite_sla', '<=', $limiteSuperior)
            ->get();

        $creadas = 0;

        foreach ($enRiesgo as $c) {
            $creadas += $this->insertar(
                proyectoId: (int) $c->proyecto_id,
                usuarioId: (int) $c->usuario_id,
                tipo: 'sla_en_riesgo',
                entidadId: (int) $c->id,
                titulo: 'SLA del ticket en riesgo',
                mensaje: sprintf(
                    'El SLA vence a las %s — acción: %s',
                    (string) $c->fecha_limite_sla,
                    mb_substr((string) $c->accion_comprometida, 0, 100),
                ),
                metadata: [
                    'caso_id' => (int) $c->caso_id,
                    'fecha_limite_sla' => (string) $c->fecha_limite_sla,
                ],
            );
        }

        return $creadas;
    }

    /** @param array<string, mixed> $metadata */
    private function insertar(
        int $proyectoId,
        int $usuarioId,
        string $tipo,
        int $entidadId,
        string $titulo,
        string $mensaje,
        array $metadata,
    ): int {
        return (int) DB::table('notificaciones')->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'destinatario_usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'entidad_tipo' => 'compromisos',
            'entidad_id' => $entidadId,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'creada_en' => now(),
        ]);
    }
}
