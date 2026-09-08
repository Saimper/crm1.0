<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los archivos que la subida de Livewire deja en disco.
 *
 * Cuando el supervisor arrastra su CSV al asistente, Livewire lo guarda entero
 * en `livewire-tmp/` antes de que nadie lo haya validado. Ahí queda el archivo
 * original del cliente, con todas sus filas, fuera de la base de datos y fuera
 * de la purga de payloads.
 *
 * Livewire tiene su propia limpieza, pero sólo se dispara cuando ALGUIEN SUBE
 * OTRO archivo: si el último import fue en marzo, el archivo de marzo sigue
 * ahí en octubre. Esto lo barre por reloj, que es como hay que barrer los
 * datos de otro.
 *
 * Doce horas por defecto: una subida que no llegó a procesarse en ese plazo es
 * un asistente abandonado, y el asistente vuelve a pedir el archivo.
 */
final class PurgarSubidasTemporalesCommand extends Command
{
    protected $signature = 'importaciones:purgar-subidas-temporales
                            {--horas=12 : Antigüedad mínima del archivo temporal}
                            {--dry-run : Solo informa qué se borraría}';

    protected $description = 'Borra del disco los archivos subidos al asistente que quedaron sin procesar';

    private const CARPETA = 'livewire-tmp';

    public function handle(): int
    {
        $horas = max(1, (int) $this->option('horas'));
        $simulacro = (bool) $this->option('dry-run');
        $limite = Carbon::now()->subHours($horas);

        $disco = Storage::disk(config('livewire.temporary_file_upload.disk') ?? config('filesystems.default'));

        if (! $disco->directoryExists(self::CARPETA)) {
            $this->info('No hay carpeta de subidas temporales: nada que barrer.');

            return self::SUCCESS;
        }

        $borrados = 0;
        $bytes = 0;

        foreach ($disco->files(self::CARPETA) as $fichero) {
            // El centinela que Livewire deja para que el directorio no
            // desaparezca del control de versiones.
            if (str_ends_with($fichero, '.gitignore')) {
                continue;
            }

            if (Carbon::createFromTimestamp($disco->lastModified($fichero))->greaterThan($limite)) {
                continue;
            }

            $bytes += $disco->size($fichero);
            $borrados++;

            if (! $simulacro) {
                $disco->delete($fichero);
            }
        }

        $this->info(sprintf(
            '%s %d archivos (%s KB) subidos hace más de %d horas.',
            $simulacro ? 'Se borrarían' : 'Borrados',
            $borrados,
            number_format($bytes / 1024, 1),
            $horas,
        ));

        return self::SUCCESS;
    }
}
