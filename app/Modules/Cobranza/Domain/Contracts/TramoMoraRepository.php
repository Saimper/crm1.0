<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\Contracts;

interface TramoMoraRepository
{
    public function resolverPorDiasMora(int $proyectoId, int $diasMora): ?int;
}
