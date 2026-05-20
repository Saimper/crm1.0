<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\ValueObjects\ResultadoDryRun;

/**
 * Output DTO para PrepararImportacionDinamica.
 */
final readonly class PrepararImportacionOutput
{
    public function __construct(
        public int $importacionId,
        public int $camposCreados,
        public int $camposReutilizados,
        public ResultadoDryRun $resultadoDryRun,
    ) {}
}
