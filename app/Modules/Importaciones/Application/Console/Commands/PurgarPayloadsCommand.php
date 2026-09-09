<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Console\Commands;

use App\Modules\Importaciones\Application\UseCases\PurgarPayloadsDeImportaciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Depura el contenido del archivo del cliente de las importaciones viejas.
 *
 * La retención por defecto sale de `config('imports.retencion_payload_dias')`,
 * y son 30 días: lo que tarda un supervisor en descargar sus filas rechazadas,
 * corregirlas y volver a subirlas, incluso volviendo de vacaciones. Pasado eso
 * el archivo no le sirve a nadie y sólo es un depósito de cédulas y teléfonos
 * esperando a que alguien se los lleve.
 *
 * Informa por importación y no sólo el total, para que se pueda comprobar que
 * no tocó lo que no debía.
 */
final class PurgarPayloadsCommand extends Command
{
    protected $signature = 'importaciones:purgar-payloads
                            {--dias= : Días de retención desde que la importación terminó (por defecto, config imports.retencion_payload_dias)}
                            {--dry-run : Solo informa cuántas filas se depurarían}';

    protected $description = 'Vacía el contenido del archivo importado (payload) de las importaciones terminadas hace más de N días';

    public function handle(PurgarPayloadsDeImportaciones $purgar): int
    {
        $dias = (int) ($this->option('dias') ?? config('imports.retencion_payload_dias'));
        $simulacro = (bool) $this->option('dry-run');

        $porImportacion = $purgar->execute($dias, $simulacro);

        if ($porImportacion === []) {
            $this->info("Nada que depurar: ninguna importación terminada hace más de {$dias} días conserva su archivo.");

            return self::SUCCESS;
        }

        foreach ($porImportacion as $importacionId => $filas) {
            $nombre = (string) DB::table('importaciones')->where('id', $importacionId)->value('nombre_archivo');
            $this->line(sprintf('  #%d %s — %d filas', $importacionId, $nombre, $filas));
        }

        $total = array_sum($porImportacion);
        $verbo = $simulacro ? 'se depurarían' : 'depuradas';

        $this->info(sprintf(
            '%d importaciones, %d filas %s (retención de %d días).',
            count($porImportacion),
            $total,
            $verbo,
            $dias,
        ));

        return self::SUCCESS;
    }
}
