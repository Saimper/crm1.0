<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Application\Services;

use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Escribe el CSV de auditoría a partir de una consulta YA RECORTADA.
 *
 * A propósito no sabe nada de permisos ni de tenants: quien llama decide el
 * alcance con `AlcanceAuditoria` y aquí sólo se serializa. Así las dos
 * exportaciones (la del proyecto y la del mandante) comparten formato sin
 * compartir —ni poder saltarse— el recorte.
 */
final readonly class ExportadorCsvAuditoria
{
    private const CABECERA = [
        'public_id', 'creada_en', 'usuario', 'entidad_tipo', 'entidad_id',
        'evento', 'ip', 'user_agent', 'cambios_json',
        'datos_antes_json', 'datos_despues_json',
    ];

    /**
     * @param  Builder  $consulta  Debe seleccionar las columnas de self::CABECERA
     *                             (más `a.id`, que se usa para paginar el chunk).
     */
    public function responder(Builder $consulta, string $nombreFichero): StreamedResponse
    {
        // El nombre lleva dentro un código de proyecto o de mandante, que son
        // datos de la base: no puede acabar en la cabecera sin limpiar.
        $nombreFichero = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombreFichero) ?? 'auditoria.csv';

        return new StreamedResponse(function () use ($consulta): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CABECERA);

            // chunk() necesita un orden estable y único: por fecha hay empates.
            $consulta->orderBy('a.id')->chunk(500, function (iterable $filas) use ($out): void {
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
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreFichero}\"",
        ]);
    }

    /**
     * Columnas que el CSV necesita. Centralizadas para que las dos
     * exportaciones no se desincronicen.
     *
     * @return list<string>
     */
    public function columnas(): array
    {
        return [
            'a.id', 'a.public_id', 'a.creada_en', 'a.entidad_tipo', 'a.entidad_id',
            'a.evento', 'a.ip', 'a.user_agent',
            'a.datos_antes', 'a.datos_despues', 'a.cambios',
            'u.name as usuario_nombre',
        ];
    }
}
