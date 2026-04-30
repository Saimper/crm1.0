<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\Enums;

enum EntidadRaiz: string
{
    case CASOS = 'casos';
    case GESTIONES = 'gestiones';
    case COMPROMISOS = 'compromisos';
    case PERSONAS = 'personas';

    public function tablaBase(): string
    {
        return $this->value;
    }

    public function ambitoCampoPersonalizado(): ?string
    {
        return match ($this) {
            self::CASOS => 'caso',
            self::GESTIONES => 'gestion',
            self::COMPROMISOS => 'compromiso',
            self::PERSONAS => null,
        };
    }
}
