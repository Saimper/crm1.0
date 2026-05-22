<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\ValueObjects\ResultadoFila;

/**
 * Wrapper que combina el resultado de una fila con los valores de
 * campos personalizados que deben persistirse en lote.
 */
final readonly class ResultadoFilaConValoresCp
{
    /**
     * @param  list<array{campo_id: int, entidad_id: int, valor: mixed, tipo: string}>  $valoresCp
     */
    public function __construct(
        public ResultadoFila $resultadoFila,
        public array $valoresCp,
        public bool $fueInsert = false,
    ) {}
}
