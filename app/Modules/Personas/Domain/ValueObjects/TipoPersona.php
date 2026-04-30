<?php

declare(strict_types=1);

namespace App\Modules\Personas\Domain\ValueObjects;

enum TipoPersona: string
{
    case FISICA = 'fisica';
    case JURIDICA = 'juridica';
}
