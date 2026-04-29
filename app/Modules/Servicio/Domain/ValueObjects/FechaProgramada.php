<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Domain\ValueObjects;

use App\Modules\Servicio\Domain\Exceptions\DatosAccionInvalidos;
use DateTimeImmutable;

final readonly class FechaProgramada
{
    public function __construct(public DateTimeImmutable $fecha)
    {
    }

    public function validarNoPasada(DateTimeImmutable $ahora): void
    {
        if ($this->fecha < $ahora) {
            throw new DatosAccionInvalidos(
                'La fecha programada no puede ser anterior a ahora: '.$this->fecha->format('Y-m-d H:i').'.'
            );
        }
    }
}
