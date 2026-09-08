<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\Exceptions;

use DomainException;

final class VentanaDeExportacionInvalida extends DomainException
{
    public static function fechasIlegibles(): self
    {
        return new self('Las fechas de la exportación deben venir como Y-m-d, las dos.');
    }

    public static function desdeDespuesDeHasta(string $desde, string $hasta): self
    {
        return new self("La fecha de inicio ({$desde}) es posterior a la de fin ({$hasta}).");
    }

    public static function demasiadoLarga(int $dias, int $maximo): self
    {
        return new self("La exportación abarca {$dias} días; el máximo es {$maximo}.");
    }
}
