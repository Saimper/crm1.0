<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Events;

final readonly class CamposPersonalizadosCreadosPorImportacion
{
    /**
     * @param list<int> $camposPersonalizadosIds
     */
    public function __construct(
        public int $importacionId,
        public int $proyectoId,
        public int $carteraId,
        public array $camposPersonalizadosIds,
    ) {}
}
