<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Gestiones\Application\DTOs\FiltrosExportacionGestiones;
use App\Modules\Gestiones\Application\Services\ExportadorCsvGestiones;
use App\Modules\Gestiones\Domain\Exceptions\VentanaDeExportacionInvalida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga las gestiones del proyecto en un rango del cliente.
 *
 * Acepta `rango` (hoy/ayer/semana/mes, el de Reportes operativos) o
 * `desde`+`hasta` (fechas de calendario, 92 días como mucho), y un
 * `usuario_id` opcional. Permiso propio `gestiones.exportar`: la ruta ya lo
 * pide con `can:` y se repite porque el controller es la última línea.
 *
 * Una ventana inadmisible vuelve a Reportes operativos con el motivo en el
 * formulario, no a un 422: la aplicación no tiene vista para ese código y en
 * producción (sin debug) Laravel pintaría la página genérica de Symfony, en
 * inglés y sin el texto. Para quien pida JSON sigue siendo un 422.
 */
final class ExportarGestionesController
{
    public function __construct(private readonly ExportadorCsvGestiones $exportador) {}

    public function __invoke(Request $request, int $proyecto_id): StreamedResponse|RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->tienePermiso('gestiones.exportar', $proyecto_id), 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first(['id', 'codigo', 'mandante_id']);
        abort_unless($proyecto !== null, 404);

        try {
            return $this->exportador->responder(
                $proyecto,
                FiltrosExportacionGestiones::desdeRequest($request),
                $usuario->carterasPermitidasParaPermiso('gestiones.exportar', $proyecto_id),
            );
        } catch (VentanaDeExportacionInvalida $e) {
            if ($request->expectsJson()) {
                abort(422, $e->getMessage());
            }

            return redirect()
                ->route('proyectos.reportes.operativos', ['proyecto_id' => $proyecto_id])
                ->withErrors(['exportar' => $e->getMessage()]);
        }
    }
}
