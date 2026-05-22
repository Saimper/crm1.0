<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;

/**
 * Input DTO para PrepararImportacionDinamica.
 */
final readonly class PrepararImportacionInput
{
    public function __construct(
        public int $importacionId,
        public EsquemaImportacion $esquema,
        public int $usuarioId,
        public bool $tienePermisoCampos,
    ) {}
}
