<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

/**
 * Input DTO para EjecutarImportacionDinamica.
 */
final readonly class EjecutarImportacionInput
{
    public function __construct(
        public int $importacionId,
        public int $chunkSize = 1000,
    ) {}
}
