<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ConfiguracionProyecto\Exceptions;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use RuntimeException;

final class PasoNoAplicableAlTipoProyecto extends RuntimeException
{
    public static function para(PasoConfiguracion $paso, TipoOperacion $tipo): self
    {
        return new self(
            "Paso {$paso->value} no aplica al tipo de operación {$tipo->value}.",
        );
    }
}
