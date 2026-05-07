<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Enums;

enum TargetImportacion: string
{
    case PERSONA = 'persona';
    case CASO_COBRANZA = 'caso_cobranza';
    case CASO_TICKET_CX = 'caso_ticket_cx';
    case CASO_LEAD_VENTA = 'caso_lead_venta';
    case CASO_SERVICIO = 'caso_servicio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PERSONA => 'Persona',
            self::CASO_COBRANZA => 'Caso de cobranza',
            self::CASO_TICKET_CX => 'Ticket CX',
            self::CASO_LEAD_VENTA => 'Lead de venta',
            self::CASO_SERVICIO => 'Caso de servicio',
        };
    }

    public function tipoEntidadDb(): string
    {
        return $this->value;
    }
}
