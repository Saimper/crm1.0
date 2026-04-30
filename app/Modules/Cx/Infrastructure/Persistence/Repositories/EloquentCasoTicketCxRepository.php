<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Persistence\Repositories;

use App\Modules\Cx\Domain\Contracts\CasoTicketCxRepository;
use App\Modules\Cx\Domain\Entities\CasoTicketCx;
use App\Modules\Cx\Domain\ValueObjects\AsuntoTicket;
use App\Modules\Cx\Domain\ValueObjects\CodigoTicket;
use App\Modules\Cx\Infrastructure\Persistence\Models\CasoTicketCxModel;
use DateTimeImmutable;

final class EloquentCasoTicketCxRepository implements CasoTicketCxRepository
{
    public function save(CasoTicketCx $ticket): CasoTicketCx
    {
        $model = CasoTicketCxModel::query()->sinScopeProyecto()->find($ticket->casoId)
            ?? new CasoTicketCxModel;

        $model->caso_id = $ticket->casoId;
        $model->proyecto_id = $ticket->proyectoId;
        $model->codigo_ticket = $ticket->codigoTicket->valor;
        $model->asunto = $ticket->asunto->valor;
        $model->descripcion = $ticket->descripcion;
        $model->categoria_ticket_id = $ticket->categoriaTicketId;
        $model->prioridad_ticket_id = $ticket->prioridadTicketId;
        $model->nivel_sla_id = $ticket->nivelSlaId;
        $model->nivel_escalamiento_id = $ticket->nivelEscalamientoId;
        $model->fecha_reporte = $ticket->fechaReporte;
        $model->fecha_limite_sla = $ticket->fechaLimiteSla;

        $model->save();

        return $ticket;
    }

    public function buscarPorCasoId(int $casoId): ?CasoTicketCx
    {
        /** @var CasoTicketCxModel|null $model */
        $model = CasoTicketCxModel::query()->sinScopeProyecto()->find($casoId);
        if ($model === null) {
            return null;
        }

        return CasoTicketCx::reconstituir(
            casoId: (int) $model->caso_id,
            proyectoId: (int) $model->proyecto_id,
            codigoTicket: new CodigoTicket((string) $model->codigo_ticket),
            asunto: new AsuntoTicket((string) $model->asunto),
            descripcion: $model->descripcion,
            categoriaTicketId: $model->categoria_ticket_id === null ? null : (int) $model->categoria_ticket_id,
            prioridadTicketId: $model->prioridad_ticket_id === null ? null : (int) $model->prioridad_ticket_id,
            nivelSlaId: $model->nivel_sla_id === null ? null : (int) $model->nivel_sla_id,
            nivelEscalamientoId: $model->nivel_escalamiento_id === null ? null : (int) $model->nivel_escalamiento_id,
            fechaReporte: $this->hidratarFechaHora($model->fecha_reporte),
            fechaLimiteSla: $model->fecha_limite_sla === null ? null : $this->hidratarFechaHora($model->fecha_limite_sla),
        );
    }

    public function existeCodigoEnProyecto(int $proyectoId, string $codigoTicket): bool
    {
        return CasoTicketCxModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('codigo_ticket', $codigoTicket)
            ->exists();
    }

    private function hidratarFechaHora(mixed $valor): DateTimeImmutable
    {
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
