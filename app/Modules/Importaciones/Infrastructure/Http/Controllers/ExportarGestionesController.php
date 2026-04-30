<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportarGestionesController
{
    public function __invoke(int $proyecto_id): StreamedResponse
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear', $proyecto_id) === true, 403);

        $proyecto = DB::table('proyectos')->where('id', $proyecto_id)->first();
        abort_unless($proyecto !== null, 404);

        $filename = "gestiones_{$proyecto->codigo}_".now()->format('Ymd_His').'.csv';

        $gestiones = DB::table('gestiones as g')
            ->leftJoin('casos as c', 'c.id', '=', 'g.caso_id')
            ->leftJoin('personas as p', 'p.id', '=', 'g.persona_id')
            ->leftJoin('resultados as r', 'r.id', '=', 'g.resultado_id')
            ->leftJoin('tipos_gestion as tg', 'tg.id', '=', 'g.tipo_gestion_id')
            ->leftJoin('canales as cn', 'cn.id', '=', 'g.canal_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.usuario_id')
            ->where('g.proyecto_id', $proyecto_id)
            ->whereNull('g.eliminada_en')
            ->select([
                'g.public_id', 'g.creada_en', 'g.duracion_segundos', 'g.notas',
                'c.public_id as caso_public_id', 'c.tipo_caso',
                'p.identificacion', 'p.tipo_persona',
                'r.codigo as resultado', 'r.es_contacto_efectivo',
                'tg.codigo as tipo_gestion',
                'cn.codigo as canal',
                'u.name as usuario',
            ])
            ->orderByDesc('g.creada_en')
            ->get();

        return new StreamedResponse(function () use ($gestiones): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'gestion_public_id', 'creada_en', 'caso_public_id', 'tipo_caso',
                'identificacion_persona', 'tipo_gestion', 'canal', 'resultado',
                'es_contacto_efectivo', 'duracion_segundos', 'usuario', 'notas',
            ]);
            foreach ($gestiones as $g) {
                fputcsv($out, [
                    (string) $g->public_id,
                    (string) $g->creada_en,
                    (string) ($g->caso_public_id ?? ''),
                    (string) ($g->tipo_caso ?? ''),
                    (string) ($g->identificacion ?? ''),
                    (string) ($g->tipo_gestion ?? ''),
                    (string) ($g->canal ?? ''),
                    (string) ($g->resultado ?? ''),
                    $g->es_contacto_efectivo ? 'sí' : 'no',
                    (string) ($g->duracion_segundos ?? ''),
                    (string) ($g->usuario ?? ''),
                    (string) ($g->notas ?? ''),
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
