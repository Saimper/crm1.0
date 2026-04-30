<?php

declare(strict_types=1);

namespace App\Modules\Casos\Domain\ValueObjects;

use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;

enum TipoCaso: string
{
    case COBRANZA = 'cobranza';
    case TICKET_CX = 'ticket_cx';
    case LEAD_VENTA = 'lead_venta';
    case SERVICIO = 'servicio';

    /** El tipo de caso aplicable a un tipo de operación de proyecto. */
    public static function desdeOperacion(TipoOperacion $operacion): self
    {
        return match ($operacion) {
            TipoOperacion::COBRANZA => self::COBRANZA,
            TipoOperacion::CX => self::TICKET_CX,
            TipoOperacion::VENTA => self::LEAD_VENTA,
            TipoOperacion::SERVICIO => self::SERVICIO,
        };
    }
}
