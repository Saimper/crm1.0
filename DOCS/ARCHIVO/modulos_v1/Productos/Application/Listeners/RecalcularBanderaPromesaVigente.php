<?php

declare(strict_types=1);

namespace App\Modules\Productos\Application\Listeners;

use App\Modules\Productos\Infrastructure\Persistence\Models\ProductoModel;
use App\Modules\Promesas\Domain\Events\EventoPromesaResuelta;

final class RecalcularBanderaPromesaVigente
{
    public function handle(EventoPromesaResuelta $evento): void
    {
        ProductoModel::query()
            ->where('id', $evento->productoId)
            ->update(['tiene_promesa_vigente' => $evento->quedanPromesasVigentes]);
    }
}
