<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Domain\ValueObjects;

enum TipoPersona: string
{
    case FISICA   = 'fisica';
    case JURIDICA = 'juridica';
}
