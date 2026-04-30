<?php

declare(strict_types=1);

namespace App\Modules\Productos\Application\Listeners;

use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Productos\Infrastructure\Persistence\Models\ProductoModel;

final class ActualizarDesnormalizadosDesdeGestion
{
    public function handle(GestionRegistrada $evento): void
    {
        ProductoModel::query()
            ->where('id', $evento->productoId)
            ->update([
                'fecha_ultima_gestion' => $evento->creadaEn,
                'resultado_ultima_gestion_id' => $evento->resultadoId,
                'usuario_ultima_gestion_id' => $evento->usuarioId,
            ]);
    }
}
