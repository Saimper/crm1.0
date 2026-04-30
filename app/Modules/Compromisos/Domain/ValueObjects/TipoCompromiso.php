<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\ValueObjects;

use App\Modules\Casos\Domain\ValueObjects\TipoCaso;

enum TipoCompromiso: string
{
    case PROMESA_PAGO = 'promesa_pago';
    case RESOLUCION_TICKET = 'resolucion_ticket';
    case CIERRE_VENTA = 'cierre_venta';
    case ACCION_SERVICIO = 'accion_servicio';

    /** Tipo de compromiso natural asociado a un tipo de caso. */
    public static function desdeTipoCaso(TipoCaso $tipoCaso): self
    {
        return match ($tipoCaso) {
            TipoCaso::COBRANZA => self::PROMESA_PAGO,
            TipoCaso::TICKET_CX => self::RESOLUCION_TICKET,
            TipoCaso::LEAD_VENTA => self::CIERRE_VENTA,
            TipoCaso::SERVICIO => self::ACCION_SERVICIO,
        };
    }
}
