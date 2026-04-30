<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Listeners;

use App\Modules\Casos\Infrastructure\Persistence\Models\CasoModel;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;

/**
 * Mantiene los campos desnormalizados de `casos` al registrarse una gestión (§4.3 CLAUDE.md v2):
 *  - fecha_ultima_gestion
 *  - resultado_ultima_gestion_id
 *  - usuario_ultima_gestion_id
 *
 * Se ejecuta dentro de la misma transacción del UseCase RegistrarGestion (§12.6).
 */
final class ActualizarDesnormalizadosDesdeGestion
{
    public function handle(GestionRegistrada $evento): void
    {
        CasoModel::query()
            ->sinScopeProyecto()
            ->where('id', $evento->casoId)
            ->update([
                'fecha_ultima_gestion' => $evento->creadaEn,
                'resultado_ultima_gestion_id' => $evento->resultadoId,
                'usuario_ultima_gestion_id' => $evento->usuarioId,
            ]);
    }
}
