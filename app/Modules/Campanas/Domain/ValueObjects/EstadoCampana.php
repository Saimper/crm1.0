<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Domain\ValueObjects;

enum EstadoCampana: string
{
    case PROGRAMADA = 'programada';
    case ACTIVA = 'activa';
    case PAUSADA = 'pausada';
    case FINALIZADA = 'finalizada';
}
