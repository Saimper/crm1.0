<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Personas\Application\DTOs\FiltrosListadoPersonas;
use App\Modules\Personas\Application\Services\ExportadorCsvPersonas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga el listado de personas del proyecto, con los filtros que el
 * supervisor tenía puestos en pantalla.
 *
 * Permiso propio, `personas.exportar`: antes la descarga colgaba de
 * `importaciones.crear` y sólo se enlazaba desde la pantalla de importaciones,
 * como si sacar el padrón fuera un paso más de cargarlo. La ruta ya lo exige
 * con `can:`; se repite aquí porque el controller es la última línea y no
 * debe fiarse de cómo lo registraron.
 */
final class ExportarPersonasController
{
    public function __construct(private readonly ExportadorCsvPersonas $exportador) {}

    public function __invoke(Request $request, int $proyecto_id): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->tienePermiso('personas.exportar', $proyecto_id), 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first(['id', 'codigo', 'mandante_id']);
        abort_unless($proyecto !== null, 404);

        return $this->exportador->responder($proyecto, FiltrosListadoPersonas::desdeRequest($request));
    }
}
