<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\Contracts;

use App\Modules\Cx\Domain\Entities\CasoTicketCx;

interface CasoTicketCxRepository
{
    public function save(CasoTicketCx $ticket): CasoTicketCx;

    public function buscarPorCasoId(int $casoId): ?CasoTicketCx;

    public function existeCodigoEnProyecto(int $proyectoId, string $codigoTicket): bool;
}
