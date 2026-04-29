<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Domain\ValueObjects;

enum EstadoPromesa: string
{
    case PENDIENTE = 'pendiente';
    case CUMPLIDA  = 'cumplida';
    case ROTA      = 'rota';
    case CANCELADA = 'cancelada';
}
