<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Compromisos\Application\DTOs\FiltrosListadoCompromisos;
use App\Modules\Compromisos\Application\Services\ExportadorCsvCompromisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga los compromisos del proyecto con los filtros del listado.
 *
 * Permiso propio `compromisos.exportar`: la ruta ya lo pide con `can:` y se
 * repite aquí porque el controller es la última línea. AUDITOR lo tiene —
 * audita actividad—, a diferencia de personas y casos.
 */
final class ExportarCompromisosController
{
    public function __construct(private readonly ExportadorCsvCompromisos $exportador) {}

    public function __invoke(Request $request, int $proyecto_id): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->tienePermiso('compromisos.exportar', $proyecto_id), 403);

        $proyecto = DB::table('proyectos')
            ->where('id', $proyecto_id)
            ->first(['id', 'codigo', 'mandante_id', 'tipo_operacion']);
        abort_unless($proyecto !== null, 404);

        return $this->exportador->responder(
            $proyecto,
            FiltrosListadoCompromisos::desdeRequest($request),
            $usuario->carterasPermitidas($proyecto_id),
        );
    }
}
