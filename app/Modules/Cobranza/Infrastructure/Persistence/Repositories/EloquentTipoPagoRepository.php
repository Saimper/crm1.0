<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Repositories;

use App\Modules\Cobranza\Domain\Contracts\TipoPagoRepository;
use App\Modules\Cobranza\Infrastructure\Persistence\Models\TipoPagoModel;

final class EloquentTipoPagoRepository implements TipoPagoRepository
{
    public function existeEnProyecto(int $proyectoId, int $tipoPagoId): bool
    {
        return TipoPagoModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('id', $tipoPagoId)
            ->where('activo', true)
            ->exists();
    }
}
