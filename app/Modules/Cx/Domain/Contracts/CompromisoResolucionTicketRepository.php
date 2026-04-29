<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\Contracts;

use App\Modules\Cx\Domain\Entities\CompromisoResolucionTicket;

interface CompromisoResolucionTicketRepository
{
    public function save(CompromisoResolucionTicket $resolucion): CompromisoResolucionTicket;

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoResolucionTicket;
}
