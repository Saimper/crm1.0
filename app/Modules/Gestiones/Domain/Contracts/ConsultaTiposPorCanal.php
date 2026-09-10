<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Contracts;

interface ConsultaTiposPorCanal
{
    /** @return list<int> */
    public function idsAdmitidos(int $proyectoId, int $canalId): array;
}
