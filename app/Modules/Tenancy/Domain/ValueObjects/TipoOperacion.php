<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ValueObjects;

enum TipoOperacion: string
{
    case COBRANZA = 'cobranza';
    case CX = 'cx';
    case VENTA = 'venta';
    case SERVICIO = 'servicio';
}
