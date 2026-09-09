<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Casos\Application\DTOs\FiltrosListadoCasos;
use App\Modules\Casos\Application\Services\ExportadorCsvCasos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga la cartera del proyecto con los filtros del listado.
 *
 * Permiso propio `casos.exportar` (la ruta ya lo pide con `can:`; se repite
 * porque el controller es la última línea). Cuando la descarga viene acotada a
 * una cartera, la cartera va como tercer argumento: un supervisor con el rol
 * restringido a ciertas carteras (F22) no puede llevarse las demás por URL.
 */
final class ExportarCasosController
{
    public function __construct(private readonly ExportadorCsvCasos $exportador) {}

    public function __invoke(Request $request, int $proyecto_id): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $filtros = FiltrosListadoCasos::desdeRequest($request);
        abort_unless($usuario->tienePermiso('casos.exportar', $proyecto_id, $filtros->carteraId), 403);

        $proyecto = DB::table('proyectos')
            ->where('id', $proyecto_id)
            ->first(['id', 'codigo', 'mandante_id', 'tipo_operacion']);
        abort_unless($proyecto !== null, 404);

        // Y aunque no venga `?cartera=`, la descarga se recorta a las carteras
        // del rol: omitir el filtro no puede ser la forma de llevarse las demás.
        return $this->exportador->responder($proyecto, $filtros, $usuario->carterasPermitidas($proyecto_id));
    }
}
