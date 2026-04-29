<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\Contracts;

interface NivelEscalamientoRepository
{
    public function existeEnProyecto(int $proyectoId, int $nivelEscalamientoId): bool;
}
