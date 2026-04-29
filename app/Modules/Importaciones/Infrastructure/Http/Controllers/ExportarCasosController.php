<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportarCasosController
{
    public function __invoke(int $proyecto_id): StreamedResponse
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear', $proyecto_id) === true, 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first();
        abort_unless($proyecto !== null, 404);

        $filename = "casos_{$proyecto->codigo}_".now()->format('Ymd_His').'.csv';

        $casos = DB::table('casos as c')
            ->leftJoin('personas as p',        'p.id',  '=', 'c.persona_id')
            ->leftJoin('carteras as ca',       'ca.id', '=', 'c.cartera_id')
            ->leftJoin('estados_caso as ec',   'ec.id', '=', 'c.estado_caso_id')
            ->leftJoin('resultados as r',      'r.id',  '=', 'c.resultado_ultima_gestion_id')
            ->where('c.proyecto_id', $proyecto_id)
            ->whereNull('c.eliminada_en')
            ->select([
                'c.public_id as caso_public_id', 'c.tipo_caso', 'c.prioridad', 'c.fecha_ingreso',
                'c.cerrado_en', 'c.fecha_ultima_gestion', 'c.tiene_compromiso_vigente',
                'ec.codigo as estado_caso',
                'ca.codigo as cartera',
                'p.tipo_persona', 'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                'r.codigo as resultado_ultimo',
            ])
            ->orderBy('c.id')
            ->get();

        return new StreamedResponse(function () use ($casos): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'caso_public_id', 'tipo_caso', 'estado_caso', 'cartera',
                'tipo_persona', 'identificacion', 'nombres', 'apellidos', 'razon_social',
                'prioridad', 'fecha_ingreso', 'fecha_ultima_gestion', 'resultado_ultimo',
                'tiene_compromiso_vigente', 'cerrado_en',
            ]);
            foreach ($casos as $c) {
                fputcsv($out, [
                    (string) $c->caso_public_id,
                    (string) $c->tipo_caso,
                    (string) ($c->estado_caso ?? ''),
                    (string) ($c->cartera ?? ''),
                    (string) ($c->tipo_persona ?? ''),
                    (string) ($c->identificacion ?? ''),
                    (string) ($c->nombres ?? ''),
                    (string) ($c->apellidos ?? ''),
                    (string) ($c->razon_social ?? ''),
                    (string) $c->prioridad,
                    (string) $c->fecha_ingreso,
                    (string) ($c->fecha_ultima_gestion ?? ''),
                    (string) ($c->resultado_ultimo ?? ''),
                    $c->tiene_compromiso_vigente ? 'sí' : 'no',
                    (string) ($c->cerrado_en ?? ''),
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
