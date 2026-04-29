<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Persistence\Repositories;

use App\Modules\Cx\Domain\Contracts\CompromisoResolucionTicketRepository;
use App\Modules\Cx\Domain\Entities\CompromisoResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;
use App\Modules\Cx\Infrastructure\Persistence\Models\CompromisoResolucionTicketModel;
use DateTimeImmutable;

final class EloquentCompromisoResolucionTicketRepository implements CompromisoResolucionTicketRepository
{
    public function save(CompromisoResolucionTicket $resolucion): CompromisoResolucionTicket
    {
        $model = CompromisoResolucionTicketModel::query()->sinScopeProyecto()->find($resolucion->compromisoId)
            ?? new CompromisoResolucionTicketModel();

        $model->compromiso_id          = $resolucion->compromisoId;
        $model->proyecto_id            = $resolucion->proyectoId;
        $model->accion_comprometida    = $resolucion->accion->valor;
        $model->fecha_limite_sla       = $resolucion->fechaLimite->fechaLimite;
        $model->nivel_escalamiento_id  = $resolucion->nivelEscalamientoId;

        $model->save();

        return $resolucion;
    }

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoResolucionTicket
    {
        /** @var CompromisoResolucionTicketModel|null $model */
        $model = CompromisoResolucionTicketModel::query()->sinScopeProyecto()->find($compromisoId);
        if ($model === null) {
            return null;
        }

        return CompromisoResolucionTicket::reconstituir(
            compromisoId:        (int) $model->compromiso_id,
            proyectoId:          (int) $model->proyecto_id,
            accion:              new AccionComprometida((string) $model->accion_comprometida),
            fechaLimite:         new FechaLimiteSla($this->hidratarFechaHora($model->fecha_limite_sla)),
            nivelEscalamientoId: $model->nivel_escalamiento_id === null ? null : (int) $model->nivel_escalamiento_id,
        );
    }

    private function hidratarFechaHora(mixed $valor): DateTimeImmutable
    {
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
