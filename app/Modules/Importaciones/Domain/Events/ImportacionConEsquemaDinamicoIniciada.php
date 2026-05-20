<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Events;

final readonly class ImportacionConEsquemaDinamicoIniciada
{
    public function __construct(
        public int $importacionId,
        public int $proyectoId,
        public int $totalColumnasCP,
        public int $totalColumnasSistema,
    ) {}
}
