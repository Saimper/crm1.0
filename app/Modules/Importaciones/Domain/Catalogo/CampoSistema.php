<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Catalogo;

/**
 * Definición de un campo del sistema disponible para mapear desde una columna CSV.
 * Pertenece a un target (persona | caso_*). El usuario nunca ve la key canónica
 * directamente: ve la etiqueta y la descripción en el wizard de mapeo.
 */
final readonly class CampoSistema
{
    public function __construct(
        public string $codigo,
        public string $etiqueta,
        public bool $requerido,
        public string $tipo,
        public ?string $catalogoCodigo = null,
        public ?string $descripcion = null,
    ) {}
}
