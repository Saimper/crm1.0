<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;

/**
 * Output DTO para InferirEsquemaDesdeHeaders.
 */
final readonly class InferirEsquemaOutput
{
    /**
     * @param list<ColumnaExcel> $columnas
     * @param list<string> $advertencias
     */
    public function __construct(
        public array $columnas,
        public ?string $sugerenciaIdentificador,
        public array $advertencias,
    ) {}
}
