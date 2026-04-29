<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FechaPromesa
{
    private function __construct(public DateTimeImmutable $fecha)
    {
    }

    public static function futura(DateTimeImmutable $fecha, DateTimeImmutable $hoy): self
    {
        $fechaDia = $fecha->setTime(0, 0);
        $hoyDia = $hoy->setTime(0, 0);

        if ($fechaDia < $hoyDia) {
            throw new InvalidArgumentException('Fecha de promesa no puede estar en el pasado.');
        }

        return new self($fechaDia);
    }

    public static function hidratar(DateTimeImmutable $fecha): self
    {
        return new self($fecha->setTime(0, 0));
    }
}
