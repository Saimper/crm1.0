<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\Listeners;

use App\Modules\Asignaciones\Infrastructure\Persistence\Models\AsignacionModel;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;

final class IniciarTrabajoDesdeGestion
{
    public function handle(GestionRegistrada $evento): void
    {
        AsignacionModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $evento->proyectoId)
            ->where('caso_id', $evento->casoId)
            ->where('usuario_id', $evento->usuarioId)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'en_trabajo']);
    }
}
