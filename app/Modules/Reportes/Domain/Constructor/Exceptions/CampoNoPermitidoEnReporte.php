<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Exceptions;

use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use DomainException;

final class CampoNoPermitidoEnReporte extends DomainException
{
    public static function clave(string $clave, EntidadRaiz $entidad): self
    {
        return new self("Campo '{$clave}' no permitido para la entidad raíz '{$entidad->value}'.");
    }
}
