<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Enums;

enum OperadorFiltro: string
{
    case IGUAL = 'igual';
    case DISTINTO = 'distinto';
    case MAYOR = 'mayor';
    case MENOR = 'menor';
    case ENTRE = 'entre';
    case CONTIENE = 'contiene';
    case EMPIEZA = 'empieza';
    case TERMINA = 'termina';
    case VACIO = 'vacio';
    case NO_VACIO = 'no_vacio';
    case EN_LISTA = 'en_lista';

    public function requiereValor(): bool
    {
        return ! in_array($this, [self::VACIO, self::NO_VACIO], true);
    }

    public function requiereDosValores(): bool
    {
        return $this === self::ENTRE;
    }

    public function requiereLista(): bool
    {
        return $this === self::EN_LISTA;
    }

    public function aSqlOperador(): string
    {
        return match ($this) {
            self::IGUAL => '=',
            self::DISTINTO => '<>',
            self::MAYOR => '>',
            self::MENOR => '<',
            self::CONTIENE, self::EMPIEZA, self::TERMINA => 'like',
            self::ENTRE => 'between',
            self::EN_LISTA => 'in',
            self::VACIO => 'is null',
            self::NO_VACIO => 'is not null',
        };
    }
}
