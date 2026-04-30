<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Exceptions;

use DomainException;

final class AgregacionRequiereAgrupacion extends DomainException
{
    public static function sinGroupBy(): self
    {
        return new self('Una definición con columnas agregadas requiere al menos una agrupación.');
    }
}
