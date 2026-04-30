<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Enums;

enum EstadoFila: string
{
    case PENDIENTE = 'pendiente';
    case PROCESADA = 'procesada';
    case DUPLICADA = 'duplicada';
    case INVALIDA = 'invalida';
    case OMITIDA = 'omitida';
}
