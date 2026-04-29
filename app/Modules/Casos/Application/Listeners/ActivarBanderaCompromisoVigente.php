<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Listeners;

use App\Modules\Casos\Infrastructure\Persistence\Models\CasoModel;
use App\Modules\Compromisos\Domain\Events\CompromisoCreado;

final class ActivarBanderaCompromisoVigente
{
    public function handle(CompromisoCreado $evento): void
    {
        CasoModel::query()
            ->sinScopeProyecto()
            ->where('id', $evento->casoId)
            ->update(['tiene_compromiso_vigente' => true]);
    }
}
