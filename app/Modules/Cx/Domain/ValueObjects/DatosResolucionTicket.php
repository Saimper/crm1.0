<?php

declare(strict_types=1);

namespace App\Modules\Cx\Domain\ValueObjects;

use App\Modules\Gestiones\Domain\ValueObjects\DatosCompromiso;

/**
 * VO concreto que acarrea los datos de una resolución/escalamiento al evento GestionRegistrada.
 * Implementa `DatosCompromiso` para que el listener de CX lo reconozca por `instanceof`.
 */
final readonly class DatosResolucionTicket implements DatosCompromiso
{
    public function __construct(
        public AccionComprometida $accion,
        public FechaLimiteSla $fechaLimite,
        public ?int $nivelEscalamientoId = null,
    ) {
    }
}
