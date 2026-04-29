<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportarCompromisosController
{
    public function __invoke(int $proyecto_id): StreamedResponse
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear', $proyecto_id) === true, 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first();
        abort_unless($proyecto !== null, 404);

        $filename = "compromisos_{$proyecto->codigo}_".now()->format('Ymd_His').'.csv';

        $compromisos = DB::table('compromisos as co')
            ->leftJoin('casos as c',    'c.id', '=', 'co.caso_id')
            ->leftJoin('personas as p', 'p.id', '=', 'c.persona_id')
            ->leftJoin('users as u',    'u.id', '=', 'co.usuario_id')
            ->where('co.proyecto_id', $proyecto_id)
            ->whereNull('co.eliminada_en')
            ->select([
                'co.public_id', 'co.tipo_compromiso', 'co.estado',
                'co.fecha_vencimiento', 'co.fecha_resolucion', 'co.creada_en',
                'c.public_id as caso_public_id', 'c.tipo_caso',
                'p.identificacion',
                'u.name as usuario',
            ])
            ->orderByDesc('co.creada_en')
            ->get();

        return new StreamedResponse(function () use ($compromisos): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'compromiso_public_id', 'tipo_compromiso', 'estado',
                'fecha_vencimiento', 'fecha_resolucion', 'creada_en',
                'caso_public_id', 'tipo_caso', 'identificacion_persona', 'usuario',
            ]);
            foreach ($compromisos as $c) {
                fputcsv($out, [
                    (string) $c->public_id,
                    (string) $c->tipo_compromiso,
                    (string) $c->estado,
                    (string) $c->fecha_vencimiento,
                    (string) ($c->fecha_resolucion ?? ''),
                    (string) $c->creada_en,
                    (string) ($c->caso_public_id ?? ''),
                    (string) ($c->tipo_caso ?? ''),
                    (string) ($c->identificacion ?? ''),
                    (string) ($c->usuario ?? ''),
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
