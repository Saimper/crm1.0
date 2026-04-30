<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Gestiones\Domain\ValueObjects\DatosCompromiso;

/**
 * VO concreto que acarrea los datos de una promesa de pago al evento GestionRegistrada.
 * Implementa la interfaz genérica `DatosCompromiso` del módulo Gestiones para que el
 * listener de Cobranza los reconozca por `instanceof`.
 */
final readonly class DatosPromesaPago implements DatosCompromiso
{
    public function __construct(
        public MontoPromesa $monto,
        public FechaPromesa $fechaVencimiento,
        public ?int $tipoPagoId = null,
    ) {}
}
