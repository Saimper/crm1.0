<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta la tabla de auditoría del proyecto como CSV streaming.
 * Acepta query params opcionales para filtrar: entidad_tipo, usuario_id, evento, desde, hasta.
 * Permiso requerido: auditoria.ver en el proyecto.
 */
final class ExportarAuditoriaController
{
    public function __invoke(Request $request, int $proyecto_id): StreamedResponse
    {
        abort_unless(auth()->user()?->tienePermiso('auditoria.ver', $proyecto_id) === true, 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first();
        abort_unless($proyecto !== null, 404);

        $entidadTipo = (string) $request->query('entidad_tipo', '');
        $usuarioId   = $request->query('usuario_id');
        $evento      = (string) $request->query('evento', '');
        $desde       = (string) $request->query('desde', '');
        $hasta       = (string) $request->query('hasta', '');

        $filename = "auditoria_{$proyecto->codigo}_".now()->format('Ymd_His').'.csv';

        $q = DB::table('auditorias as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.proyecto_id', $proyecto_id)
            ->select([
                'a.public_id', 'a.creada_en', 'a.entidad_tipo', 'a.entidad_id',
                'a.evento', 'a.ip', 'a.user_agent',
                'a.datos_antes', 'a.datos_despues', 'a.cambios',
                'u.name as usuario_nombre',
            ])
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

        return new StreamedResponse(function () use ($q): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'public_id', 'creada_en', 'usuario', 'entidad_tipo', 'entidad_id',
                'evento', 'ip', 'user_agent', 'cambios_json',
                'datos_antes_json', 'datos_despues_json',
            ]);

            $q->orderBy('a.id')->chunk(500, function ($filas) use ($out): void {
                foreach ($filas as $a) {
                    fputcsv($out, [
                        (string) $a->public_id,
                        (string) $a->creada_en,
                        (string) ($a->usuario_nombre ?? ''),
                        (string) $a->entidad_tipo,
                        (string) $a->entidad_id,
                        (string) $a->evento,
                        (string) ($a->ip ?? ''),
                        (string) ($a->user_agent ?? ''),
                        (string) ($a->cambios ?? ''),
                        (string) ($a->datos_antes ?? ''),
                        (string) ($a->datos_despues ?? ''),
                    ]);
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
