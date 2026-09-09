<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\Columnas;

/**
 * Una columna específica del tipo de compromiso en la exportación.
 *
 * `expresion` es SQL ya calificado y declarado en el servidor —`cti.monto`,
 * `cat.nombre`—: nadie fuera de este catálogo aporta SQL. `cabecera` es el
 * nombre de la columna en el CSV.
 */
final readonly class ColumnaCompromiso
{
    public function __construct(
        public string $cabecera,
        public string $expresion,
    ) {}

    public function alias(): string
    {
        return 'col_'.$this->cabecera;
    }
}
