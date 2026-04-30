<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Exceptions;

use App\Modules\Reportes\Domain\Constructor\Enums\OperadorFiltro;
use App\Modules\Reportes\Domain\Constructor\Enums\TipoCampoReporte;
use DomainException;

final class OperadorIncompatibleConTipo extends DomainException
{
    public static function combinar(OperadorFiltro $op, TipoCampoReporte $tipo, string $campo): self
    {
        return new self("Operador '{$op->value}' incompatible con tipo '{$tipo->value}' del campo '{$campo}'.");
    }
}
