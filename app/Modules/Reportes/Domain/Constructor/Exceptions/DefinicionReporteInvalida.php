<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Exceptions;

use DomainException;

final class DefinicionReporteInvalida extends DomainException
{
    public static function sinColumnas(): self
    {
        return new self('La definición del reporte debe tener al menos una columna.');
    }

    public static function codigoVacio(): self
    {
        return new self('El código de la definición no puede estar vacío.');
    }

    public static function nombreVacio(): self
    {
        return new self('El nombre de la definición no puede estar vacío.');
    }
}
