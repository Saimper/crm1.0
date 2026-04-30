<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Enums;

enum Agregacion: string
{
    case COUNT = 'count';
    case SUM = 'sum';
    case AVG = 'avg';
    case MAX = 'max';
    case MIN = 'min';

    public function aSql(): string
    {
        return strtoupper($this->value);
    }

    public function requiereTipoNumerico(): bool
    {
        return in_array($this, [self::SUM, self::AVG], true);
    }
}
