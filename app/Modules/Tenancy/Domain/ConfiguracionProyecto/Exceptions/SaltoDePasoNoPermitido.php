<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ConfiguracionProyecto\Exceptions;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use RuntimeException;

final class SaltoDePasoNoPermitido extends RuntimeException
{
    public static function haciaPaso(PasoConfiguracion $paso): self
    {
        return new self(
            "No se puede saltar al paso {$paso->value}: hay pasos previos obligatorios sin completar.",
        );
    }
}
