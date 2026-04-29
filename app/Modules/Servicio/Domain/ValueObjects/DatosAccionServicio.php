<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\ValueObjects;

use App\Modules\Gestiones\Domain\ValueObjects\DatosCompromiso;

/**
 * VO concreto que acarrea datos de una acción de servicio al evento GestionRegistrada.
 * Implementa `DatosCompromiso` para que el listener de Servicio lo reconozca por `instanceof`.
 */
final readonly class DatosAccionServicio implements DatosCompromiso
{
    public function __construct(
        public DescripcionAccion $descripcion,
        public FechaProgramada $fechaProgramada,
        public ?int $tipoAccionServicioId = null,
        public ?string $tecnicoAsignado = null,
    ) {
    }
}
