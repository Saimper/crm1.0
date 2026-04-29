<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\ValueObjects;

/**
 * Contrato abstracto para los datos que acompañan a un compromiso nacido de una gestión.
 * Cada especialización (cobranza, cx, venta, servicio) implementa su VO concreto con los datos
 * específicos (p. ej. `DatosPromesaPago` en cobranza con monto + fecha + tipo de pago).
 * El evento `GestionRegistrada` acarrea un `?DatosCompromiso` y los listeners de cada tipo de
 * operación filtran por `instanceof` antes de actuar.
 */
interface DatosCompromiso
{
}
