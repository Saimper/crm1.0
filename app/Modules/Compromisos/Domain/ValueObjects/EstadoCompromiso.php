<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Domain\ValueObjects;

enum EstadoCompromiso: string
{
    case PENDIENTE = 'pendiente';
    case CUMPLIDO = 'cumplido';
    case ROTO = 'roto';
    case CANCELADO = 'cancelado';
}
