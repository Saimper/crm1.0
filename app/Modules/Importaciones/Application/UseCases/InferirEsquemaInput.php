<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Enums\TargetImportacion;

/**
 * Input DTO para InferirEsquemaDesdeHeaders.
 */
final readonly class InferirEsquemaInput
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $filasMuestra
     */
    public function __construct(
        public array $headers,
        public array $filasMuestra,
        public TargetImportacion $target,
        public int $proyectoId,
        public ?int $carteraId,
    ) {}
}
