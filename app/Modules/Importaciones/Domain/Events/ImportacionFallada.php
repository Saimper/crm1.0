<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Events;

final readonly class ImportacionFallada
{
    public function __construct(
        public int $importacionId,
        public int $proyectoId,
        public string $error,
    ) {}
}
