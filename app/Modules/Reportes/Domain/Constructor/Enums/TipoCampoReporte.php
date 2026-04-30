<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Enums;

enum TipoCampoReporte: string
{
    case TEXTO = 'texto';
    case NUMERO = 'numero';
    case DECIMAL = 'decimal';
    case FECHA = 'fecha';
    case FECHA_HORA = 'fecha_hora';
    case BOOLEANO = 'booleano';
    case ENUM = 'enum';
    case MONEDA = 'moneda';

    /**
     * @return list<OperadorFiltro>
     */
    public function operadoresCompatibles(): array
    {
        return match ($this) {
            self::TEXTO, self::ENUM => [
                OperadorFiltro::IGUAL,
                OperadorFiltro::DISTINTO,
                OperadorFiltro::CONTIENE,
                OperadorFiltro::EMPIEZA,
                OperadorFiltro::TERMINA,
                OperadorFiltro::VACIO,
                OperadorFiltro::NO_VACIO,
                OperadorFiltro::EN_LISTA,
            ],
            self::NUMERO, self::DECIMAL, self::MONEDA => [
                OperadorFiltro::IGUAL,
                OperadorFiltro::DISTINTO,
                OperadorFiltro::MAYOR,
                OperadorFiltro::MENOR,
                OperadorFiltro::ENTRE,
                OperadorFiltro::VACIO,
                OperadorFiltro::NO_VACIO,
                OperadorFiltro::EN_LISTA,
            ],
            self::FECHA, self::FECHA_HORA => [
                OperadorFiltro::IGUAL,
                OperadorFiltro::DISTINTO,
                OperadorFiltro::MAYOR,
                OperadorFiltro::MENOR,
                OperadorFiltro::ENTRE,
                OperadorFiltro::VACIO,
                OperadorFiltro::NO_VACIO,
            ],
            self::BOOLEANO => [
                OperadorFiltro::IGUAL,
                OperadorFiltro::DISTINTO,
                OperadorFiltro::VACIO,
                OperadorFiltro::NO_VACIO,
            ],
        };
    }

    public function esNumerico(): bool
    {
        return in_array($this, [self::NUMERO, self::DECIMAL, self::MONEDA], true);
    }

    public function aceptaOperador(OperadorFiltro $operador): bool
    {
        return in_array($operador, $this->operadoresCompatibles(), true);
    }
}
