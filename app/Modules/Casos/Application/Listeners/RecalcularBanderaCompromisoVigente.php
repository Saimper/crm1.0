<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Listeners;

use App\Modules\Casos\Infrastructure\Persistence\Models\CasoModel;
use App\Modules\Compromisos\Domain\Events\EventoCompromisoResuelto;

final class RecalcularBanderaCompromisoVigente
{
    public function handle(EventoCompromisoResuelto $evento): void
    {
        CasoModel::query()
            ->sinScopeProyecto()
            ->where('id', $evento->casoId)
            ->update(['tiene_compromiso_vigente' => $evento->quedanCompromisosVigentesEnCaso]);
    }
}
