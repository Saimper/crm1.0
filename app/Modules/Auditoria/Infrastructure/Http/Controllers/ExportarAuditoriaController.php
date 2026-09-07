<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Auditoria\Application\Services\ExportadorCsvAuditoria;
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

        $entidadTipo = (string) $request->query('entidad_tipo', '');
        $usuarioId = $request->query('usuario_id');
        $evento = (string) $request->query('evento', '');
        $desde = (string) $request->query('desde', '');
        $hasta = (string) $request->query('hasta', '');

        $filename = "auditoria_{$proyecto->codigo}_".now()->format('Ymd_His').'.csv';

        $q = DB::table('auditorias as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            // El recorte va PRIMERO y no depende de ningún parámetro: los
            // filtros de abajo sólo pueden estrechar lo que ya está acotado.
            ->where('a.proyecto_id', $proyecto_id)
            ->select($this->exportador->columnas())
            ->orderByDesc('a.creada_en');

        if ($entidadTipo !== '') {
            $q->where('a.entidad_tipo', $entidadTipo);
        }
        if ($usuarioId !== null && $usuarioId !== '') {
            $q->where('a.usuario_id', (int) $usuarioId);
        }
        if ($evento !== '') {
            $q->where('a.evento', $evento);
        }
        if ($desde !== '') {
            $q->where('a.creada_en', '>=', $desde.' 00:00:00');
        }
        if ($hasta !== '') {
            $q->where('a.creada_en', '<=', $hasta.' 23:59:59');
        }

        return $this->exportador->responder($q, $filename);
    }
}
