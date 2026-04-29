<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportarPersonasController
{
    public function __invoke(int $proyecto_id): StreamedResponse
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear', $proyecto_id) === true, 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first();
        abort_unless($proyecto !== null, 404);

        $codigo = (string) $proyecto->codigo;
        $fecha = now()->format('Ymd_His');
        $filename = "personas_{$codigo}_{$fecha}.csv";

        $personas = DB::table('personas as p')
            ->leftJoin('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyecto_id)
            ->whereNull('p.eliminada_en')
            ->orderBy('p.id')
            ->select([
                'p.tipo_persona',
                'ti.codigo as tipo_identificacion_codigo',
                'p.identificacion',
                'p.nombres',
                'p.apellidos',
                'p.razon_social',
                'p.fecha_nacimiento',
            ])
            ->get();

        return new StreamedResponse(function () use ($personas): void {
            $out = fopen('php://output', 'w');
            // BOM para Excel.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'tipo_persona', 'tipo_identificacion_codigo', 'identificacion',
                'nombres', 'apellidos', 'razon_social', 'fecha_nacimiento',
            ]);
            foreach ($personas as $p) {
                fputcsv($out, [
                    (string) ($p->tipo_persona ?? ''),
                    (string) ($p->tipo_identificacion_codigo ?? ''),
                    (string) ($p->identificacion ?? ''),
                    (string) ($p->nombres ?? ''),
                    (string) ($p->apellidos ?? ''),
                    (string) ($p->razon_social ?? ''),
                    (string) ($p->fecha_nacimiento ?? ''),
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
