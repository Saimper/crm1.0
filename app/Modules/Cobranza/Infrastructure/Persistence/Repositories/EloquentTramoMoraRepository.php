<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Infrastructure\Persistence\Repositories;

use App\Modules\Cobranza\Domain\Contracts\TramoMoraRepository;
use App\Modules\Cobranza\Infrastructure\Persistence\Models\TramoMoraModel;

final class EloquentTramoMoraRepository implements TramoMoraRepository
{
    public function resolverPorDiasMora(int $proyectoId, int $diasMora): ?int
    {
        /** @var TramoMoraModel|null $modelo */
        $modelo = TramoMoraModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->where('dias_desde', '<=', $diasMora)
            ->where(function ($q) use ($diasMora): void {
                $q->whereNull('dias_hasta')->orWhere('dias_hasta', '>=', $diasMora);
            })
            ->orderBy('dias_desde', 'desc')
            ->first();

        return $modelo?->id === null ? null : (int) $modelo->id;
    }
}
