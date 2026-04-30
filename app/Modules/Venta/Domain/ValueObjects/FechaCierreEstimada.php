<?php

declare(strict_types=1);

namespace App\Modules\Venta\Domain\ValueObjects;

use App\Modules\Venta\Domain\Exceptions\DatosCierreInvalidos;
use DateTimeImmutable;

final readonly class FechaCierreEstimada
{
    public function __construct(public DateTimeImmutable $fecha) {}

    public function validarNoPasada(DateTimeImmutable $hoy): void
    {
        $soloFecha = $this->fecha->setTime(0, 0, 0);
        $hoyTruncado = $hoy->setTime(0, 0, 0);
        if ($soloFecha < $hoyTruncado) {
            throw new DatosCierreInvalidos(
                'La fecha estimada de cierre no puede ser anterior a hoy: '.$this->fecha->format('Y-m-d').'.'
            );
        }
    }
}
