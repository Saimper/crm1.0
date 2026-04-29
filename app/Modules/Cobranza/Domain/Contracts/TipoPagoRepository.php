<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\Contracts;

interface TipoPagoRepository
{
    /**
     * Verifica que el tipo de pago exista y pertenezca al proyecto indicado.
     */
    public function existeEnProyecto(int $proyectoId, int $tipoPagoId): bool;
}
