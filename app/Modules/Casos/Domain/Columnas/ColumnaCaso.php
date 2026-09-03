<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\Columnas;

/**
 * Definición de una columna disponible en el listado de casos.
 *
 * `expresion` es SQL ya calificado y declarado aquí en el servidor: el usuario
 * solo elige claves de este catálogo, nunca aporta SQL (mismo criterio que la
 * whitelist del constructor de reportes, §Reportes F32).
 */
final readonly class ColumnaCaso
{
    public function __construct(
        public string $clave,
        public string $etiqueta,
        public string $expresion,
        public bool $numerica = false,
        public bool $fija = false,
        public ?string $tipoOperacion = null,
    ) {}

    public function alias(): string
    {
        return 'col_'.$this->clave;
    }
}
