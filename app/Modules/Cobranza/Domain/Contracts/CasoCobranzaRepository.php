<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\Contracts;

use App\Modules\Cobranza\Domain\Entities\CasoCobranza;

interface CasoCobranzaRepository
{
    public function save(CasoCobranza $caso): CasoCobranza;

    public function buscarPorCasoId(int $casoId): ?CasoCobranza;

    public function existeNumeroPrestamoEnProyecto(int $proyectoId, string $numeroPrestamo): bool;
}
