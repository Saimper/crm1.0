<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

use Generator;

final class ResultadoEjecucionReporte
{
    /**
     * @param  list<array{clave: string, etiqueta: string}>  $cabeceras
     * @param  Generator<int, array<string,mixed>>  $filas
     */
    public function __construct(
        public readonly array $cabeceras,
        public readonly Generator $filas,
    ) {}
}
