<?php

declare(strict_types=1);

namespace App\Modules\Cx\Infrastructure\Persistence\Repositories;

use App\Modules\Cx\Domain\Contracts\NivelEscalamientoRepository;
use App\Modules\Cx\Infrastructure\Persistence\Models\NivelEscalamientoModel;

final class EloquentNivelEscalamientoRepository implements NivelEscalamientoRepository
{
    public function existeEnProyecto(int $proyectoId, int $nivelEscalamientoId): bool
    {
        return NivelEscalamientoModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('id', $nivelEscalamientoId)
            ->where('activo', true)
            ->exists();
    }
}
