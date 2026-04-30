<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Events;

final readonly class ImportacionTerminada
{
    public function __construct(
        public int $importacionId,
        public int $proyectoId,
        public int $procesadas,
        public int $validas,
        public int $invalidas,
        public int $omitidas,
        public int $duplicadas,
    ) {}
}
