<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Exceptions;

use DomainException;

final class MandanteSsoNoConfigurado extends DomainException
{
    public static function crear(int $mandanteId): self
    {
        return new self("Mandante {$mandanteId} no tiene sso_secret configurado.");
    }
}
