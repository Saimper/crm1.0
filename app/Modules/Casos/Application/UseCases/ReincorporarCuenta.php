<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\UseCases;

use App\Modules\Casos\Domain\Contracts\ReincorporacionDeCuenta;
use App\Modules\Usuarios\Domain\Contracts\AccesoACuenta;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/** Reuse an archived debt without duplicating it or rewriting historical ownership. */
final readonly class ReincorporarCuenta implements ReincorporacionDeCuenta
{
    public function __construct(private ConnectionInterface $db, private AccesoACuenta $acceso) {}

    public function execute(int $proyectoId, int $casoId, int $carteraDestinoId, int $importacionId, int $usuarioId): void
    {
        $this->db->transaction(function () use ($proyectoId, $casoId, $carteraDestinoId, $importacionId, $usuarioId): void {
            $caso = $this->db->table('casos')->where('proyecto_id', $proyectoId)->where('id', $casoId)
                ->whereNull('eliminada_en')->lockForUpdate()->first();
            if ($caso === null) {
                throw new DomainException('La cuenta archivada no está disponible para reincorporar.');
            }
            $importacion = $this->db->table('importaciones')->where('proyecto_id', $proyectoId)->where('id', $importacionId)->first();
            $solicitud = $importacion !== null && is_string($importacion->esquema) ? json_decode($importacion->esquema, true) : null;
            if ($importacion === null || ! is_array($solicitud)
                || ! in_array($importacion->estado, ['preparada', 'procesando'], true)
                || ! in_array($importacion->modo, ['insert', 'update', 'upsert', 'merge', 'overwrite', 'skip_duplicados'], true)
                || ($solicitud['reincorporar_archivadas'] ?? false) !== true
                || (int) ($solicitud['autorizado_por_id'] ?? 0) !== $usuarioId
                || (int) ($solicitud['proyecto_id'] ?? 0) !== $proyectoId
                || (int) ($solicitud['cartera_id'] ?? 0) !== $carteraDestinoId
                || ($solicitud['target'] ?? '') !== 'caso_'.$caso->tipo_caso
                || $importacion->tipo_entidad !== 'caso_'.$caso->tipo_caso) {
                throw new DomainException('La importación no autoriza reincorporar esta cuenta a la cartera seleccionada.');
            }
            $destino = $this->db->table('carteras')->where('proyecto_id', $proyectoId)->where('id', $carteraDestinoId)
                ->where('activo', true)->whereNull('eliminada_en')->lockForUpdate()->first();
            if ($destino === null) {
                throw new DomainException('La cartera de destino no está activa en este proyecto.');
            }
            if (! $this->db->table('personas')->where('proyecto_id', $proyectoId)->where('id', $caso->persona_id)->whereNull('eliminada_en')->exists()) {
                throw new DomainException('La persona de esta cuenta está archivada.');
            }
            if ((int) $caso->cartera_id === $carteraDestinoId) {
                return;
            }
            $origen = $this->db->table('carteras')->where('proyecto_id', $proyectoId)
                ->where('id', $caso->cartera_id)->lockForUpdate()->first();
            if ($origen === null || ((bool) $origen->activo && $origen->eliminada_en === null)) {
                throw new DomainException('La cuenta pertenece a otra cartera activa; conserva su responsable y no se duplicará.');
            }
            if (! $this->acceso->puedeReincorporar($usuarioId, $proyectoId, (int) $caso->cartera_id, $carteraDestinoId)) {
                throw new DomainException('No tienes permiso para reincorporar cuentas entre estas carteras.');
            }
            $tabla = match ((string) $caso->tipo_caso) {
                'cobranza' => 'casos_cobranza', 'ticket_cx' => 'casos_ticket_cx',
                'lead_venta' => 'casos_lead_venta', 'servicio' => 'casos_servicio',
                default => throw new DomainException('Tipo de cuenta no admitido.'),
            };
            $detalle = $this->db->table($tabla)->where('proyecto_id', $proyectoId)->where('caso_id', $casoId)->lockForUpdate()->first();
            if ($detalle === null) {
                throw new DomainException('La cuenta no tiene los datos de su tipo de operación.');
            }
            $datos = (array) $detalle;
            $asignacion = $this->db->table('asignaciones as a')->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
                ->where('a.proyecto_id', $proyectoId)->where('a.caso_id', $casoId)
                ->select(['a.usuario_id', 'u.name'])->lockForUpdate()->first();
            $ahora = now();
            $snapshot = [
                'tipo_caso' => $caso->tipo_caso,
                'referencia' => $datos['numero_prestamo'] ?? $datos['codigo_ticket'] ?? $datos['codigo_lead'] ?? $datos['codigo_servicio'] ?? '',
                'moneda' => $datos['moneda'] ?? null,
                'saldo_total' => $datos['saldo_total'] ?? $datos['valor_estimado'] ?? null,
                'dias_mora' => $datos['dias_mora'] ?? null,
                'fecha_ingreso' => $caso->fecha_ingreso,
                'estado_caso_nombre' => $this->db->table('estados_caso')->where('proyecto_id', $proyectoId)->where('id', $caso->estado_caso_id)->value('nombre'),
                'cartera_nombre' => $origen->nombre,
                'asesor_nombre' => $asignacion?->name,
                'datos_tipo' => $datos,
                'last_gestion_id' => (int) $this->db->table('gestiones')->where('proyecto_id', $proyectoId)->where('caso_id', $casoId)->max('id'),
                'last_compromiso_id' => (int) $this->db->table('compromisos')->where('proyecto_id', $proyectoId)->where('caso_id', $casoId)->max('id'),
            ];
            $this->db->table('caso_cartera_movimientos')->insert([
                'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId, 'caso_id' => $casoId,
                'cartera_origen_id' => $caso->cartera_id, 'cartera_destino_id' => $carteraDestinoId,
                'importacion_id' => $importacionId, 'usuario_id' => $usuarioId,
                'asesor_anterior_id' => $asignacion?->usuario_id, 'trasladada_en' => $ahora,
                'instantanea' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            ]);
            $this->db->table('casos')->where('proyecto_id', $proyectoId)->where('id', $casoId)
                ->update(['cartera_id' => $carteraDestinoId, 'actualizada_en' => $ahora]);
            // The old advisor is preserved; the supervisor explicitly decides any reassignment.
        });
    }
}
