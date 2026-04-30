<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Cobranza\Domain\Exceptions\DatosPromesaInvalidos;
use DateTimeImmutable;

final readonly class FechaPromesa
{
    public function __construct(public DateTimeImmutable $fecha) {}

    /**
     * Valida que la fecha sea hoy o posterior al registrar la promesa.
     * Se pasa `hoy` como parámetro para permitir tests deterministas.
     */
    public function validarNoPasada(DateTimeImmutable $hoy): void
    {
        $soloFecha = $this->fecha->setTime(0, 0, 0);
        $hoyTruncado = $hoy->setTime(0, 0, 0);
        if ($soloFecha < $hoyTruncado) {
            throw new DatosPromesaInvalidos(
                'La fecha de la promesa no puede ser anterior a hoy: '.$this->fecha->format('Y-m-d').'.'
            );
        }
    }
}
