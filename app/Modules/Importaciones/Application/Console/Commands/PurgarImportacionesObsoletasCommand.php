<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Console\Commands;

use App\Modules\Importaciones\Application\UseCases\PurgarImportacionesObsoletas;
use Illuminate\Console\Command;

final class PurgarImportacionesObsoletasCommand extends Command
{
    protected $signature = 'importaciones:purgar-obsoletas {--dias=7 : Antigüedad mínima en días de importaciones nunca lanzadas}';

    protected $description = 'Elimina importaciones en estado pendiente/preparada con más de N días, junto con sus filas.';

    public function handle(PurgarImportacionesObsoletas $purgar): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $eliminadas = $purgar->execute($dias);

        $this->info("Importaciones obsoletas eliminadas: {$eliminadas} (sin lanzar hace más de {$dias} días).");

        return self::SUCCESS;
    }
}
