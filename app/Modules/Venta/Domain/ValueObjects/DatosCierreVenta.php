<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\ValueObjects;

use App\Modules\Gestiones\Domain\ValueObjects\DatosCompromiso;

/**
 * VO concreto que acarrea los datos de un cierre de venta al evento GestionRegistrada.
 * Implementa `DatosCompromiso` para que el listener de Venta lo reconozca por `instanceof`.
 */
final readonly class DatosCierreVenta implements DatosCompromiso
{
    public function __construct(
        public MontoCierre $monto,
        public FechaCierreEstimada $fechaEstimada,
        public ?int $etapaEmbudoId = null,
    ) {}
}
