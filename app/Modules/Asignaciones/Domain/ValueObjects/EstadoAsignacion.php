<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Domain\ValueObjects;

enum EstadoAsignacion: string
{
    case PENDIENTE  = 'pendiente';
    case EN_TRABAJO = 'en_trabajo';
    case CERRADA    = 'cerrada';
}
