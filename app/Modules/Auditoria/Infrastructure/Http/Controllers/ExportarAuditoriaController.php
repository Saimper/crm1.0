<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Auditoria\Application\Services\ExportadorCsvAuditoria;
use App\Modules\Auditoria\Application\Services\FiltrosAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta la auditoría de UN proyecto como CSV streaming.
 *
 * Permiso requerido: `auditoria.exportar` en ese proyecto — no `auditoria.ver`.
 * El permiso existía en el seeder desde el principio y no lo exigía NADIE en
 * todo el código: ruta y controlador se conformaban con `auditoria.ver`, así
 * que un SUPERVISOR —a quien el seeder le niega `auditoria.exportar`— se
 * descargaba el historial entero del cliente. Ver es mirar una pantalla que se
 * queda ahí; exportar es sacar los datos del sistema.
 */
final class ExportarAuditoriaController
{
    public function __construct(private readonly ExportadorCsvAuditoria $exportador) {}

    public function __invoke(Request $request, int $proyecto_id): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->tienePermiso('auditoria.exportar', $proyecto_id), 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first();
        abort_unless($proyecto !== null, 404);

        $filtros = FiltrosAuditoria::desdeQueryString($request);

        $filename = "auditoria_{$proyecto->codigo}_".now()->format('Ymd_His').'.csv';

        // El recorte por proyecto va dentro de la consulta y PRIMERO; los
        // filtros de abajo sólo pueden estrechar lo que ya está acotado.
        $q = $this->exportador->consultaDelProyecto($proyecto_id);

        $filtros->aplicar($q, 'a');

        return $this->exportador->responder(
            $q,
            $filename,
            $filtros,
            proyectoId: $proyecto_id,
            mandanteId: (int) $proyecto->mandante_id,
        );
    }
}
