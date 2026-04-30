<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\Exceptions;

use DomainException;

final class ReglaViolada extends DomainException
{
    public static function fechaAnteriorAMinimo(string $etiqueta, string $minimoLegible): self
    {
        return new self("El campo «{$etiqueta}» debe ser igual o posterior a {$minimoLegible}.");
    }

    public static function fechaPosteriorAMaximo(string $etiqueta, string $maximoLegible): self
    {
        return new self("El campo «{$etiqueta}» debe ser igual o anterior a {$maximoLegible}.");
    }
}
